<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AchatFournisseur;
use App\Models\Animal;
use App\Models\User;
use App\Repositories\AchatFournisseurRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AchatFournisseurService
{
    public function __construct(private readonly AchatFournisseurRepository $repository) {}

    public function paginate(?string $boucherieId = null, ?string $fournisseurId = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($boucherieId, $fournisseurId, $perPage);
    }

    public function findById(string $id): AchatFournisseur
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data, ?string $boucherieId, int $userId, ?string $fournisseurId, User $user): AchatFournisseur
    {
        return DB::transaction(function () use ($data, $boucherieId, $userId, $fournisseurId, $user) {
            $attachmentService = app(AttachmentService::class);
            $animaux = $data['animaux'] ?? [];
            unset($data['animaux']);

            $data['boucherie_id']  = $boucherieId;
            $data['fournisseur_id'] = $fournisseurId;
            $data['user_id']       = $userId;

            // Ignorer le montant soumis — toujours calculer côté serveur
            unset($data['montant_total']);
            $data['montant_total'] = 0;

            $achat = $this->repository->create($data);

            foreach ($animaux as $animalData) {
                $attachmentIds = $animalData['attachment_ids'] ?? [];
                unset($animalData['attachment_ids']);

                $animalData['achat_fournisseur_id'] = $achat->id;
                if ($fournisseurId) {
                    $animalData['fournisseur_id'] = $fournisseurId;
                } else {
                    $animalData['boucherie_id'] = $boucherieId;
                }

                $animal = Animal::create($animalData);

                if (is_array($attachmentIds) && $attachmentIds !== []) {
                    $attachmentService->linkTo($animal, $attachmentIds, $user);
                }
            }

            $montantCalcule = array_sum(array_column($animaux, 'prix_achat'));
            $achat->update(['montant_total' => $montantCalcule]);

            return $achat->fresh(['animaux.attachments']);
        });
    }
}
