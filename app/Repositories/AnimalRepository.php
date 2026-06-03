<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Animal;
use Illuminate\Pagination\LengthAwarePaginator;

class AnimalRepository
{
    public function __construct(private readonly Animal $model) {}

    public function paginate(?string $boucherieId = null, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->when($boucherieId, fn ($q) => $q->where('boucherie_id', $boucherieId))
            ->with(['fournisseur', 'attachments', 'abattage'])
            ->latest();

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        return $query->paginate($perPage);
    }

    public function paginateByFournisseurUser(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->whereHas('fournisseur', fn ($q) => $q->where('user_id', $userId))
            ->with(['fournisseur', 'attachments', 'abattage'])
            ->latest();

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        return $query->paginate($perPage);
    }

    public function findOrFail(string $id): Animal
    {
        return $this->model->query()->with(['fournisseur', 'attachments', 'abattage', 'achatFournisseur'])->findOrFail($id);
    }

    public function create(array $data): Animal
    {
        return $this->model->query()->create($data);
    }
}
