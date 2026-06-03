<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Abattage;
use App\Models\Distribution;
use App\Models\LigneVente;
use App\Models\Stock;
use App\Models\Vente;
use App\Models\Versement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatsService
{
    private function periodeStart(string $periode): Carbon
    {
        return match ($periode) {
            'jour'    => Carbon::today()->startOfDay(),
            'semaine' => Carbon::now()->subDays(7),
            'annee'   => Carbon::now()->subYear(),
            default   => Carbon::now()->subDays(30),
        };
    }

    public function forAdmin(string $periode): array
    {
        $from = $this->periodeStart($periode);

        $ventesBase = Vente::where('created_at', '>=', $from);

        $versementsParStatut = Versement::select('statut', DB::raw('count(*) as total'), DB::raw('sum(montant) as montant'))
            ->groupBy('statut')
            ->get()
            ->keyBy('statut');

        $topProduits = LigneVente::select('produit_id', DB::raw('sum(quantite) as quantite_vendue'), DB::raw('sum(sous_total) as chiffre_affaires'))
            ->whereHas('vente', fn ($q) => $q->where('created_at', '>=', $from))
            ->with('produit:id,nom')
            ->groupBy('produit_id')
            ->orderByDesc('chiffre_affaires')
            ->limit(5)
            ->get();

        $topBoucheries = Vente::select('boucherie_id', DB::raw('count(*) as nb_ventes'), DB::raw('sum(montant_total) as chiffre_affaires'))
            ->where('created_at', '>=', $from)
            ->with('boucherie:id,nom')
            ->groupBy('boucherie_id')
            ->orderByDesc('chiffre_affaires')
            ->limit(5)
            ->get();

        $topFournisseurs = Versement::select('fournisseur_user_id', DB::raw('sum(montant) as montant_recu'))
            ->where('statut', 'valide')
            ->with('fournisseurUser:id,name')
            ->groupBy('fournisseur_user_id')
            ->orderByDesc('montant_recu')
            ->limit(5)
            ->get();

        return [
            'periode' => $periode,
            'ventes'  => [
                'total'         => (clone $ventesBase)->count(),
                'montant_total' => (float) (clone $ventesBase)->sum('montant_total'),
                'montant_moyen' => (float) round((clone $ventesBase)->avg('montant_total') ?? 0, 2),
            ],
            'stocks' => [
                'total_references' => Stock::count(),
                'alertes_rupture'  => Stock::whereRaw('quantite <= seuil_alerte')->count(),
            ],
            'versements' => [
                'en_attente' => [
                    'count'   => (int) ($versementsParStatut['en_attente']->total ?? 0),
                    'montant' => (float) ($versementsParStatut['en_attente']->montant ?? 0),
                ],
                'valides' => [
                    'count'   => (int) ($versementsParStatut['valide']->total ?? 0),
                    'montant' => (float) ($versementsParStatut['valide']->montant ?? 0),
                ],
                'rejetes' => [
                    'count'   => (int) ($versementsParStatut['rejete']->total ?? 0),
                    'montant' => (float) ($versementsParStatut['rejete']->montant ?? 0),
                ],
            ],
            'top_produits' => $topProduits->map(fn ($l) => [
                'produit'          => $l->produit?->nom,
                'quantite_vendue'  => (float) $l->quantite_vendue,
                'chiffre_affaires' => (float) $l->chiffre_affaires,
            ])->values(),
            'top_boucheries' => $topBoucheries->map(fn ($v) => [
                'boucherie'        => $v->boucherie?->nom,
                'nb_ventes'        => (int) $v->nb_ventes,
                'chiffre_affaires' => (float) $v->chiffre_affaires,
            ])->values(),
            'top_fournisseurs' => $topFournisseurs->map(fn ($v) => [
                'fournisseur'  => $v->fournisseurUser?->name,
                'montant_recu' => (float) $v->montant_recu,
            ])->values(),
        ];
    }

    public function forBoucher(string $boucherieId, string $periode): array
    {
        $from = $this->periodeStart($periode);

        $ventesBase = Vente::where('boucherie_id', $boucherieId)->where('created_at', '>=', $from);

        $versementsParStatut = Versement::where('boucherie_id', $boucherieId)
            ->select('statut', DB::raw('count(*) as total'), DB::raw('sum(montant) as montant'))
            ->groupBy('statut')
            ->get()
            ->keyBy('statut');

        $distributionsParStatut = Distribution::where('boucherie_id', $boucherieId)
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->get()
            ->keyBy('statut');

        $stocksEnAlerte = Stock::where('boucherie_id', $boucherieId)
            ->whereRaw('quantite <= seuil_alerte')
            ->with('produit:id,nom')
            ->get(['produit_id', 'quantite', 'seuil_alerte']);

        $topProduits = LigneVente::select('produit_id', DB::raw('sum(quantite) as quantite_vendue'), DB::raw('sum(sous_total) as chiffre_affaires'))
            ->whereHas('vente', fn ($q) => $q->where('boucherie_id', $boucherieId)->where('created_at', '>=', $from))
            ->with('produit:id,nom')
            ->groupBy('produit_id')
            ->orderByDesc('chiffre_affaires')
            ->limit(5)
            ->get();

        $topClients = Vente::select('client_id', DB::raw('count(*) as nb_ventes'), DB::raw('sum(montant_total) as total_achats'))
            ->where('boucherie_id', $boucherieId)
            ->where('created_at', '>=', $from)
            ->whereNotNull('client_id')
            ->with('client:id,nom')
            ->groupBy('client_id')
            ->orderByDesc('total_achats')
            ->limit(5)
            ->get();

        return [
            'periode' => $periode,
            'ventes'  => [
                'total'         => (clone $ventesBase)->count(),
                'montant_total' => (float) (clone $ventesBase)->sum('montant_total'),
                'montant_moyen' => (float) round((clone $ventesBase)->avg('montant_total') ?? 0, 2),
            ],
            'stocks' => [
                'total_references'   => Stock::where('boucherie_id', $boucherieId)->count(),
                'alertes_rupture'    => $stocksEnAlerte->count(),
                'produits_en_alerte' => $stocksEnAlerte->map(fn ($s) => [
                    'produit'      => $s->produit?->nom,
                    'quantite'     => (float) $s->quantite,
                    'seuil_alerte' => (float) $s->seuil_alerte,
                ])->values(),
            ],
            'distributions' => [
                'en_attente' => (int) ($distributionsParStatut['en_attente']->total ?? 0),
                'acceptees'  => (int) ($distributionsParStatut['acceptee']->total ?? 0),
                'rejetees'   => (int) ($distributionsParStatut['rejetee']->total ?? 0),
            ],
            'versements' => [
                'en_attente' => [
                    'count'   => (int) ($versementsParStatut['en_attente']->total ?? 0),
                    'montant' => (float) ($versementsParStatut['en_attente']->montant ?? 0),
                ],
                'valides' => [
                    'count'   => (int) ($versementsParStatut['valide']->total ?? 0),
                    'montant' => (float) ($versementsParStatut['valide']->montant ?? 0),
                ],
                'rejetes' => [
                    'count'   => (int) ($versementsParStatut['rejete']->total ?? 0),
                    'montant' => (float) ($versementsParStatut['rejete']->montant ?? 0),
                ],
            ],
            'top_produits' => $topProduits->map(fn ($l) => [
                'produit'          => $l->produit?->nom,
                'quantite_vendue'  => (float) $l->quantite_vendue,
                'chiffre_affaires' => (float) $l->chiffre_affaires,
            ])->values(),
            'top_clients' => $topClients->map(fn ($v) => [
                'client'       => $v->client?->nom,
                'nb_ventes'    => (int) $v->nb_ventes,
                'total_achats' => (float) $v->total_achats,
            ])->values(),
        ];
    }

    public function forFournisseur(string $userId, string $periode): array
    {
        $from = $this->periodeStart($periode);

        $abattagesBase = Abattage::whereHas('animal.fournisseur', fn ($q) => $q->where('user_id', $userId))
            ->where('created_at', '>=', $from);

        $distributionsParStatut = Distribution::where('fournisseur_user_id', $userId)
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->get()
            ->keyBy('statut');

        $versementsParStatut = Versement::where('fournisseur_user_id', $userId)
            ->select('statut', DB::raw('count(*) as total'), DB::raw('sum(montant) as montant'))
            ->groupBy('statut')
            ->get()
            ->keyBy('statut');

        $totalDu    = (float) Versement::where('fournisseur_user_id', $userId)->whereIn('statut', ['en_attente', 'valide'])->sum('montant');
        $totalPercu = (float) Versement::where('fournisseur_user_id', $userId)->where('statut', 'valide')->sum('montant');

        $topBoucheries = Distribution::where('fournisseur_user_id', $userId)
            ->select('boucherie_id', DB::raw('count(*) as nb_distributions'), DB::raw('sum(quantite) as quantite_totale'))
            ->with('boucherie:id,nom')
            ->groupBy('boucherie_id')
            ->orderByDesc('quantite_totale')
            ->limit(5)
            ->get();

        return [
            'periode'   => $periode,
            'abattages' => [
                'total'           => (clone $abattagesBase)->count(),
                'poids_total_kg'  => (float) (clone $abattagesBase)->sum('poids_carcasse_kg'),
                'rendement_moyen' => round((float) ((clone $abattagesBase)->avg('rendement_pct') ?? 0), 2),
            ],
            'distributions' => [
                'en_attente' => (int) ($distributionsParStatut['en_attente']->total ?? 0),
                'acceptees'  => (int) ($distributionsParStatut['acceptee']->total ?? 0),
                'rejetees'   => (int) ($distributionsParStatut['rejetee']->total ?? 0),
            ],
            'versements' => [
                'en_attente' => [
                    'count'   => (int) ($versementsParStatut['en_attente']->total ?? 0),
                    'montant' => (float) ($versementsParStatut['en_attente']->montant ?? 0),
                ],
                'valides' => [
                    'count'   => (int) ($versementsParStatut['valide']->total ?? 0),
                    'montant' => (float) ($versementsParStatut['valide']->montant ?? 0),
                ],
                'rejetes' => [
                    'count'   => (int) ($versementsParStatut['rejete']->total ?? 0),
                    'montant' => (float) ($versementsParStatut['rejete']->montant ?? 0),
                ],
                'total_du'    => $totalDu,
                'total_percu' => $totalPercu,
            ],
            'top_boucheries_clientes' => $topBoucheries->map(fn ($d) => [
                'boucherie'        => $d->boucherie?->nom,
                'nb_distributions' => (int) $d->nb_distributions,
                'quantite_totale'  => (float) $d->quantite_totale,
            ])->values(),
        ];
    }
}
