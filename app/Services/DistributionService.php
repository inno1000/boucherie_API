<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Distribution;
use App\Models\DistributionLigne;
use App\Repositories\DistributionRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionService
{
    public function __construct(
        private readonly DistributionRepository $repository,
        private readonly FournisseurBoucherieService $fournisseurBoucherieService,
    ) {}

    public function paginateByFournisseur(
        int $fournisseurUserId,
        ?string $statut = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->repository->paginateByFournisseur($fournisseurUserId, $statut, $perPage);
    }

    public function paginateByBoucherie(
        string $boucherieId,
        ?string $statut = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->repository->paginateByBoucherie($boucherieId, $statut, $perPage);
    }

    public function paginateAll(?string $statut = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateAll($statut, $perPage);
    }

    public function findById(string $id): Distribution
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data, int $fournisseurUserId): Distribution
    {
        return DB::transaction(function () use ($data, $fournisseurUserId) {
            $lignes = $data['lignes'] ?? [];
            unset($data['lignes']);

            $this->fournisseurBoucherieService->assertBoucherieServedByFournisseurUser(
                (string) $data['boucherie_id'],
                $fournisseurUserId,
            );

            if (! empty($lignes)) {
                $data['quantite'] = collect($lignes)->sum(fn ($l) => (float) $l['poids_kg']);
                $data['produit_id'] ??= null;
            } elseif (empty($data['produit_id']) || ! isset($data['quantite'])) {
                throw ValidationException::withMessages([
                    'lignes' => ['Indiquez des lignes par catégorie ou un produit avec une quantité.'],
                ]);
            }

            $data['fournisseur_user_id'] = $fournisseurUserId;
            $data['statut']              = 'en_attente';

            $distribution = $this->repository->create($data);

            foreach ($lignes as $ligne) {
                DistributionLigne::create([
                    'distribution_id' => $distribution->id,
                    'categorie'       => $ligne['categorie'],
                    'poids_kg'        => $ligne['poids_kg'],
                    'prix_par_kg'     => $ligne['prix_par_kg'] ?? null,
                ]);
            }

            return $distribution->fresh(['lignes', 'boucherie', 'abattage', 'produit']);
        });
    }

    public function rejeter(string $id, int $fournisseurUserId): Distribution
    {
        $distribution = $this->repository->findOrFail($id);

        if ($distribution->fournisseur_user_id !== $fournisseurUserId) {
            abort(403, 'Vous ne pouvez pas modifier cette distribution.');
        }

        if ($distribution->statut !== 'en_attente') {
            throw ValidationException::withMessages([
                'statut' => ['Seule une distribution en attente peut être annulée.'],
            ]);
        }

        return $this->repository->update($id, ['statut' => 'rejetee']);
    }
}
