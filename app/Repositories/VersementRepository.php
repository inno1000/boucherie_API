<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Versement;
use Illuminate\Pagination\LengthAwarePaginator;

class VersementRepository
{
    public function __construct(private readonly Versement $model) {}

    public function paginateByBoucherie(
        string $boucherieId,
        ?string $statut = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->model->query()
            ->where('boucherie_id', $boucherieId)
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->with(['fournisseurUser', 'validePar'])
            ->latest()
            ->paginate($perPage);
    }

    public function paginateByFournisseur(
        int $fournisseurUserId,
        ?string $statut = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->model->query()
            ->where('fournisseur_user_id', $fournisseurUserId)
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->with(['boucherie', 'validePar'])
            ->latest()
            ->paginate($perPage);
    }

    public function paginateAll(?string $statut = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->query()
            ->when($statut, fn ($q) => $q->where('statut', $statut))
            ->with(['boucherie', 'fournisseurUser', 'validePar'])
            ->latest()
            ->paginate($perPage);
    }

    public function findOrFail(string $id): Versement
    {
        return $this->model->query()
            ->with(['boucherie', 'fournisseurUser', 'validePar', 'attachments'])
            ->findOrFail($id);
    }

    public function create(array $data): Versement
    {
        return $this->model->query()->create($data);
    }

    public function update(string $id, array $data): Versement
    {
        $versement = $this->findOrFail($id);
        $versement->update($data);
        return $versement->fresh(['boucherie', 'fournisseurUser', 'validePar']);
    }
}
