<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AchatFournisseur;
use App\Models\Animal;
use App\Models\Attachment;
use App\Repositories\AnimalRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AnimalService
{
    public function __construct(private readonly AnimalRepository $repository) {}

    public function paginate(?string $boucherieId = null, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($boucherieId, $filters, $perPage);
    }

    public function paginateByFournisseurUser(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginateByFournisseurUser($userId, $filters, $perPage);
    }

    public function findById(string $id): Animal
    {
        return $this->repository->findOrFail($id);
    }

    public function update(string $id, array $data): Animal
    {
        return DB::transaction(function () use ($id, $data) {
            $animal = $this->repository->findOrFail($id);

            if ($animal->statut !== 'en_attente') {
                abort(422, 'Seuls les animaux non abattus peuvent être modifiés.');
            }

            $animal->update($data);
            $this->syncAchatMontant($animal->achat_fournisseur_id);

            return $animal->fresh(['fournisseur', 'attachments', 'abattage']);
        });
    }

    public function delete(string $id): void
    {
        DB::transaction(function () use ($id) {
            $animal = $this->repository->findOrFail($id);

            if ($animal->statut !== 'en_attente') {
                abort(422, 'Seuls les animaux non abattus peuvent être supprimés.');
            }

            if ($animal->abattage()->exists()) {
                abort(422, 'Cet animal a déjà un abattage enregistré.');
            }

            $achatId = $animal->achat_fournisseur_id;

            foreach ($animal->attachments as $attachment) {
                $this->deleteAttachmentFile($attachment);
            }
            $animal->attachments()->delete();
            $animal->delete();

            $this->syncAchatMontant($achatId);
        });
    }

    private function syncAchatMontant(?string $achatId): void
    {
        if (! $achatId) {
            return;
        }

        $montant = Animal::query()
            ->where('achat_fournisseur_id', $achatId)
            ->sum('prix_achat');

        AchatFournisseur::query()
            ->whereKey($achatId)
            ->update(['montant_total' => $montant]);
    }

    private function deleteAttachmentFile(Attachment $attachment): void
    {
        if ($attachment->path) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
    }
}
