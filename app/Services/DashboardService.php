<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Abattage;
use App\Models\Animal;
use App\Models\Boucherie;
use App\Models\Distribution;
use App\Models\Reception;
use App\Models\User;
use App\Models\Vente;
use App\Models\Versement;
use Carbon\Carbon;
class DashboardService
{
    public function __construct(private readonly StatsService $statsService) {}

    public function todayForUser(User $user): array
    {
        $date = Carbon::today()->toDateString();

        if ($user->hasRole('admin')) {
            return $this->forAdmin($date);
        }

        if ($user->hasRole('fournisseur')) {
            return $this->forFournisseur($user, $date);
        }

        if ($user->boucherie_id === null) {
            abort(403, 'Action non autorisée.');
        }

        return $this->forBoucher($user, $date);
    }

    private function forAdmin(string $date): array
    {
        $stats = $this->statsService->forAdmin('jour');

        $versementsEnAttente = (int) ($stats['versements']['en_attente']['count'] ?? 0);

        $tasks = [];
        if ($versementsEnAttente > 0) {
            $tasks[] = $this->task(
                'versements_pending',
                'high',
                $versementsEnAttente,
                '/reports/financial',
                'review_versements',
            );
        }

        $tasks[] = $this->task(
            'admin_users',
            'medium',
            User::count(),
            '/admin/users/list',
            'manage_users',
        );

        return [
            'role'    => 'admin',
            'date'    => $date,
            'summary' => [
                'users_total'            => User::count(),
                'butcheries_total'       => Boucherie::count(),
                'versements_en_attente'  => $versementsEnAttente,
                'alertes_stock'          => (int) ($stats['stocks']['alertes_rupture'] ?? 0),
            ],
            'tasks'  => $tasks,
            'recent' => $this->recentForAdmin(),
        ];
    }

    private function forBoucher(User $user, string $date): array
    {
        $boucherieId = (string) $user->boucherie_id;
        $stats       = $this->statsService->forBoucher($boucherieId, 'jour');

        $alertesStock = (int) ($stats['stocks']['alertes_rupture'] ?? 0);
        $receptionsPending = Distribution::query()
            ->where('boucherie_id', $boucherieId)
            ->where('statut', 'en_attente')
            ->whereDoesntHave('reception')
            ->count();

        $versementsEnAttente = (int) ($stats['versements']['en_attente']['count'] ?? 0);
        $ventesJour          = (int) ($stats['ventes']['total'] ?? 0);
        $caJour              = (float) ($stats['ventes']['montant_total'] ?? 0);

        $tasks = [];

        if ($alertesStock > 0) {
            $tasks[] = $this->task(
                'stock_alerts',
                'high',
                $alertesStock,
                '/stock/management',
                'review_stock',
            );
        }

        if ($receptionsPending > 0) {
            $tasks[] = $this->task(
                'receptions_pending',
                'high',
                $receptionsPending,
                '/stock/reception',
                'receive_distribution',
            );
        }

        if ($versementsEnAttente > 0) {
            $tasks[] = $this->task(
                'versements_pending',
                'medium',
                $versementsEnAttente,
                '/versement/liste',
                'review_versements',
            );
        }

        $tasks[] = $this->task(
            'record_sale',
            'low',
            max(0, $ventesJour),
            '/vente/enregistrer',
            'record_sale',
        );

        return [
            'role'    => 'butcher',
            'date'    => $date,
            'summary' => [
                'ventes_jour'           => $ventesJour,
                'ca_jour'               => $caJour,
                'alertes_stock'         => $alertesStock,
                'receptions_en_attente' => $receptionsPending,
                'versements_en_attente' => $versementsEnAttente,
            ],
            'tasks'  => $tasks,
            'recent' => $this->recentForBoucher($boucherieId),
        ];
    }

    private function forFournisseur(User $user, string $date): array
    {
        $userId = (string) $user->id;
        $stats  = $this->statsService->forFournisseur($userId, 'jour');

        $animauxEnAttente = Animal::query()
            ->whereHas('fournisseur', fn ($q) => $q->where('user_id', $user->id))
            ->where('statut', 'en_attente')
            ->count();

        $versementsEnAttente = (int) ($stats['versements']['en_attente']['count'] ?? 0);
        $distributionsEnAttente = (int) ($stats['distributions']['en_attente'] ?? 0);

        $tasks = [];

        if ($animauxEnAttente > 0) {
            $tasks[] = $this->task(
                'animals_pending',
                'high',
                $animauxEnAttente,
                '/abattage/animaux',
                'review_animals',
            );
        }

        if ($versementsEnAttente > 0) {
            $tasks[] = $this->task(
                'versements_to_validate',
                'high',
                $versementsEnAttente,
                '/versement/liste',
                'validate_versements',
            );
        }

        if ($distributionsEnAttente > 0) {
            $tasks[] = $this->task(
                'distributions_pending',
                'medium',
                $distributionsEnAttente,
                '/abattage/liste',
                'review_distributions',
            );
        }

        $tasks[] = $this->task(
            'record_slaughter',
            'low',
            (int) ($stats['abattages']['total'] ?? 0),
            '/abattage/enregistrer',
            'record_slaughter',
        );

        return [
            'role'    => 'supplier',
            'date'    => $date,
            'summary' => [
                'animaux_en_attente'      => $animauxEnAttente,
                'versements_en_attente'   => $versementsEnAttente,
                'distributions_en_attente'=> $distributionsEnAttente,
                'abattages_jour'          => (int) ($stats['abattages']['total'] ?? 0),
            ],
            'tasks'  => $tasks,
            'recent' => $this->recentForFournisseur($userId),
        ];
    }

    /**
     * @return array{id: string, priority: string, count: int, href: string, action: string}
     */
    private function task(
        string $id,
        string $priority,
        int $count,
        string $href,
        string $action,
    ): array {
        return [
            'id'       => $id,
            'priority' => $priority,
            'count'    => $count,
            'href'     => $href,
            'action'   => $action,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function recentForBoucher(string $boucherieId): array
    {
        $items = [];

        Vente::query()
            ->where('boucherie_id', $boucherieId)
            ->latest()
            ->limit(3)
            ->get(['id', 'montant_total', 'created_at'])
            ->each(function (Vente $v) use (&$items) {
                $items[] = [
                    'kind'      => 'sale',
                    'id'        => $v->id,
                    'label'     => number_format((float) $v->montant_total, 0, ',', ' ').' FCFA',
                    'at'        => $v->created_at?->toIso8601String(),
                ];
            });

        Reception::query()
            ->whereHas('distribution', fn ($q) => $q->where('boucherie_id', $boucherieId))
            ->latest()
            ->limit(2)
            ->get(['id', 'created_at'])
            ->each(function (Reception $r) use (&$items) {
                $items[] = [
                    'kind'  => 'reception',
                    'id'    => $r->id,
                    'label' => null,
                    'at'    => $r->created_at?->toIso8601String(),
                ];
            });

        return $this->sortRecent($items, 5);
    }

    /** @return list<array<string, mixed>> */
    private function recentForFournisseur(string $userId): array
    {
        $items = [];

        Abattage::query()
            ->whereHas('animal.fournisseur', fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->limit(2)
            ->get(['id', 'created_at'])
            ->each(function (Abattage $a) use (&$items) {
                $items[] = [
                    'kind'  => 'slaughter',
                    'id'    => $a->id,
                    'label' => null,
                    'at'    => $a->created_at?->toIso8601String(),
                ];
            });

        Distribution::query()
            ->where('fournisseur_user_id', $userId)
            ->latest()
            ->limit(2)
            ->get(['id', 'statut', 'created_at'])
            ->each(function (Distribution $d) use (&$items) {
                $items[] = [
                    'kind'  => 'distribution',
                    'id'    => $d->id,
                    'label' => $d->statut,
                    'at'    => $d->created_at?->toIso8601String(),
                ];
            });

        Versement::query()
            ->where('fournisseur_user_id', $userId)
            ->latest()
            ->limit(2)
            ->get(['id', 'montant', 'statut', 'created_at'])
            ->each(function (Versement $v) use (&$items) {
                $items[] = [
                    'kind'  => 'versement',
                    'id'    => $v->id,
                    'label' => number_format((float) $v->montant, 0, ',', ' ').' FCFA',
                    'at'    => $v->created_at?->toIso8601String(),
                ];
            });

        return $this->sortRecent($items, 5);
    }

    /** @return list<array<string, mixed>> */
    private function recentForAdmin(): array
    {
        $items = [];

        Versement::query()
            ->latest()
            ->limit(3)
            ->get(['id', 'montant', 'statut', 'created_at'])
            ->each(function (Versement $v) use (&$items) {
                $items[] = [
                    'kind'  => 'versement',
                    'id'    => $v->id,
                    'label' => $v->statut,
                    'at'    => $v->created_at?->toIso8601String(),
                ];
            });

        Vente::query()
            ->latest()
            ->limit(2)
            ->get(['id', 'montant_total', 'created_at'])
            ->each(function (Vente $v) use (&$items) {
                $items[] = [
                    'kind'  => 'sale',
                    'id'    => $v->id,
                    'label' => number_format((float) $v->montant_total, 0, ',', ' ').' FCFA',
                    'at'    => $v->created_at?->toIso8601String(),
                ];
            });

        return $this->sortRecent($items, 5);
    }

    /** @param list<array<string, mixed>> $items */
    private function sortRecent(array $items, int $limit): array
    {
        usort($items, fn ($a, $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));

        return array_slice($items, 0, $limit);
    }
}
