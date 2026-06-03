<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Distribution;
use Illuminate\Pagination\LengthAwarePaginator;

class DistributionRepository
{
    public function __construct(private readonly Distribution $model) {}

    public function paginateByFournisseur(int $fournisseurUserId, ?string $statut = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($statut)
            ->where('fournisseur_user_id', $fournisseurUserId)
            ->with(['abattage', 'boucherie', 'produit', 'lignes', 'reception'])
            ->latest()
            ->paginate($perPage);
    }

    public function paginateByBoucherie(string $boucherieId, ?string $statut = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($statut)
            ->where('boucherie_id', $boucherieId)
            ->with(['abattage', 'fournisseurUser', 'produit', 'lignes', 'reception'])
            ->latest()
            ->paginate($perPage);
    }

    public function paginateAll(?string $statut = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($statut)
            ->with(['abattage', 'boucherie', 'produit', 'lignes', 'fournisseurUser', 'reception'])
            ->latest()
            ->paginate($perPage);
    }

    private function baseQuery(?string $statut): \Illuminate\Database\Eloquent\Builder
    {
        $query = $this->model->query();

        if ($statut !== null && $statut !== '') {
            $query->where('statut', $statut);
        }

        return $query;
    }

    public function findOrFail(string $id): Distribution
    {
        return $this->model->query()
            ->with(['abattage.animal', 'boucherie', 'produit', 'lignes', 'fournisseurUser', 'reception'])
            ->findOrFail($id);
    }

    public function create(array $data): Distribution
    {
        return $this->model->query()->create($data);
    }

    public function update(string $id, array $data): Distribution
    {
        $distribution = $this->findOrFail($id);
        $distribution->update($data);
        return $distribution->fresh(['abattage', 'boucherie', 'produit', 'lignes', 'reception']);
    }
}
