<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AchatFournisseur;
use Illuminate\Pagination\LengthAwarePaginator;

class AchatFournisseurRepository
{
    public function __construct(private readonly AchatFournisseur $model) {}

    public function paginate(?string $boucherieId = null, ?string $fournisseurId = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->query()
            ->when($boucherieId,   fn ($q) => $q->where('boucherie_id',  $boucherieId))
            ->when($fournisseurId, fn ($q) => $q->where('fournisseur_id', $fournisseurId))
            ->with(['fournisseur', 'user'])
            ->select(['id', 'boucherie_id', 'fournisseur_id', 'user_id', 'reference', 'montant_total', 'date_achat', 'created_at', 'updated_at'])
            ->latest()
            ->paginate($perPage);
    }

    public function findOrFail(string $id): AchatFournisseur
    {
        return $this->model->query()->with(['fournisseur', 'animaux.attachments', 'user'])->findOrFail($id);
    }

    public function create(array $data): AchatFournisseur
    {
        return $this->model->query()->create($data);
    }
}
