<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Boucherie;
use Illuminate\Pagination\LengthAwarePaginator;

class BoucherieRepository
{
    public function __construct(private readonly Boucherie $model) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->query()
            ->select(['id', 'nom', 'adresse', 'ville', 'telephone', 'actif', 'created_at', 'updated_at'])
            ->latest()
            ->paginate($perPage);
    }

    /** Boucheries rattachées au fournisseur via `fournisseur_boucherie`. */
    public function paginateForFournisseurUser(int $fournisseurUserId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->query()
            ->select(['id', 'nom', 'adresse', 'ville', 'telephone', 'actif', 'created_at', 'updated_at'])
            ->whereHas(
                'fournisseurAssigne',
                fn ($q) => $q->where('fournisseurs.user_id', $fournisseurUserId),
            )
            ->latest()
            ->paginate($perPage);
    }

    public function findOrFail(string $id): Boucherie
    {
        return $this->model->query()->findOrFail($id);
    }

    public function create(array $data): Boucherie
    {
        return $this->model->query()->create($data);
    }

    public function update(string $id, array $data): Boucherie
    {
        $boucherie = $this->findOrFail($id);
        $boucherie->update($data);
        return $boucherie;
    }

    public function delete(string $id): void
    {
        $this->findOrFail($id)->delete();
    }
}
