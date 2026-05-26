<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @responseField id string ID de la distribution.
 * @responseField abattage_id string ID de l'abattage source.
 * @responseField abattage object Détail de l'abattage (si chargé).
 * @responseField fournisseur_user_id integer ID de l'utilisateur fournisseur.
 * @responseField boucherie_id string ID de la boucherie destinataire.
 * @responseField boucherie object Boucherie destinataire (si chargée).
 * @responseField produit_id string ID du produit distribué.
 * @responseField produit object Détail du produit (si chargé).
 * @responseField quantite number Quantité distribuée.
 * @responseField statut string Statut : en_attente, acceptee, rejetee.
 * @responseField notes string Notes libres.
 * @responseField reception object Réception associée (si existante).
 * @responseField created_at string Date de création (ISO 8601).
 * @responseField updated_at string Date de modification (ISO 8601).
 */
class DistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'abattage_id'         => $this->abattage_id,
            'abattage'            => $this->whenLoaded('abattage', fn () => new AbattageResource($this->abattage)),
            'fournisseur_user_id' => $this->fournisseur_user_id,
            'boucherie_id'        => $this->boucherie_id,
            'boucherie'           => $this->whenLoaded('boucherie', fn () => new BoucherieResource($this->boucherie)),
            'produit_id'          => $this->produit_id,
            'produit'             => $this->whenLoaded('produit', fn () => new ProduitResource($this->produit)),
            'quantite'            => $this->quantite,
            'statut'              => $this->statut,
            'notes'               => $this->notes,
            'reception'           => $this->whenLoaded('reception', fn () => new ReceptionResource($this->reception)),
            'lignes'              => $this->whenLoaded('lignes'),
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),
        ];
    }
}
