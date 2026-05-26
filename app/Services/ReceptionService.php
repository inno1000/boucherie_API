<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MouvementStock;
use App\Models\Reception;
use App\Models\ReceptionLigne;
use App\Models\Stock;
use App\Models\StockCategorie;
use App\Repositories\DistributionRepository;
use App\Repositories\ReceptionRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceptionService
{
    public function __construct(
        private readonly ReceptionRepository $repository,
        private readonly DistributionRepository $distributionRepository,
    ) {}

    public function paginateByBoucherie(?string $boucherieId = null): LengthAwarePaginator
    {
        return $this->repository->paginateByBoucherie($boucherieId);
    }

    public function paginateByFournisseur(int $fournisseurUserId): LengthAwarePaginator
    {
        return $this->repository->paginateByFournisseur($fournisseurUserId);
    }

    public function findById(string $id): Reception
    {
        return $this->repository->findOrFail($id);
    }

    public function create(array $data, ?string $boucherieId, int $userId): Reception
    {
        return DB::transaction(function () use ($data, $boucherieId, $userId) {
            $lignesReception = $data['lignes'] ?? [];
            unset($data['lignes']);

            $distribution = $this->distributionRepository->findOrFail($data['distribution_id']);
            $distribution->load('lignes');

            if ($distribution->boucherie_id !== $boucherieId) {
                throw ValidationException::withMessages([
                    'distribution_id' => ['Cette distribution ne vous est pas destinée.'],
                ]);
            }

            if ($distribution->statut !== 'en_attente') {
                throw ValidationException::withMessages([
                    'distribution_id' => ['Cette distribution a déjà été réceptionnée ou rejetée.'],
                ]);
            }

            $reception = $this->repository->create([
                'distribution_id' => $distribution->id,
                'boucherie_id'    => $boucherieId,
                'user_id'         => $userId,
                'quantite_recue'  => $data['quantite_recue'],
                'date_reception'  => $data['date_reception'],
                'notes'           => $data['notes'] ?? null,
            ]);

            $this->distributionRepository->update($distribution->id, ['statut' => 'acceptee']);

            if (empty($lignesReception) && $distribution->lignes->isNotEmpty()) {
                $lignesReception = $distribution->lignes->map(fn ($l) => [
                    'categorie'        => $l->categorie,
                    'poids_kg_attendu' => (float) $l->poids_kg,
                    'poids_kg_recu'    => (float) $l->poids_kg,
                ])->all();
            }

            if (! empty($lignesReception)) {
                foreach ($lignesReception as $ligne) {
                    ReceptionLigne::create([
                        'reception_id'     => $reception->id,
                        'categorie'        => $ligne['categorie'],
                        'poids_kg_attendu' => $ligne['poids_kg_attendu'] ?? null,
                        'poids_kg_recu'    => $ligne['poids_kg_recu'],
                    ]);

                    $stockCat = StockCategorie::firstOrCreate(
                        ['boucherie_id' => $boucherieId, 'categorie' => $ligne['categorie']],
                        ['poids_kg_disponible' => 0],
                    );
                    $stockCat->increment('poids_kg_disponible', (float) $ligne['poids_kg_recu']);
                }
            } elseif ($distribution->produit_id) {
                $stock = Stock::firstOrCreate(
                    ['boucherie_id' => $boucherieId, 'produit_id' => $distribution->produit_id],
                    ['quantite' => 0, 'seuil_alerte' => 0, 'abattage_id' => $distribution->abattage_id],
                );

                $stock->increment('quantite', (float) $data['quantite_recue']);

                MouvementStock::create([
                    'stock_id' => $stock->id,
                    'user_id'  => $userId,
                    'type'     => 'entree',
                    'quantite' => $data['quantite_recue'],
                    'motif'    => "Réception distribution #{$distribution->id}",
                ]);

                $produit = $distribution->produit;
                if ($produit?->categorie) {
                    $stockCat = StockCategorie::firstOrCreate(
                        ['boucherie_id' => $boucherieId, 'categorie' => $produit->categorie],
                        ['poids_kg_disponible' => 0],
                    );
                    $stockCat->increment('poids_kg_disponible', (float) $data['quantite_recue']);
                }
            } else {
                throw ValidationException::withMessages([
                    'lignes' => ['Aucune ligne de catégorie sur cette distribution.'],
                ]);
            }

            return $reception->fresh(['distribution.produit', 'distribution.lignes', 'distribution.fournisseurUser', 'lignes', 'attachments']);
        });
    }
}
