<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @responseField id integer ID de l'animal.
 * @responseField boucherie_id integer ID de la boucherie.
 * @responseField achat_fournisseur_id integer ID de l'achat fournisseur associé.
 * @responseField espece string Espèce animale (ex: bovin, ovin, caprin).
 * @responseField poids_vif_kg number Poids vif en kilogrammes.
 * @responseField prix_achat number Prix d'achat en FCFA.
 * @responseField numero_tag string Numéro de tag d'identification.
 * @responseField statut string Statut de l'animal : en_attente, abattu, vendu.
 * @responseField created_at string Date de création (ISO 8601).
 * @responseField updated_at string Date de modification (ISO 8601).
 */
class AnimalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'boucherie_id'          => $this->boucherie_id,
            'achat_fournisseur_id'  => $this->achat_fournisseur_id,
            'espece'                => $this->espece,
            'poids_vif_kg'          => $this->poids_vif_kg,
            'prix_achat'            => $this->prix_achat,
            'numero_tag'            => $this->numero_tag,
            'statut'                => $this->statut,
            'attachments'           => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at'            => $this->created_at?->toISOString(),
            'updated_at'            => $this->updated_at?->toISOString(),
        ];
    }
}
