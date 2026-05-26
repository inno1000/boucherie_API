<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCategorieProduit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAbattageRequest extends FormRequest
{
    use ValidatesCategorieProduit;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'animal_id'         => ['required', 'uuid', 'exists:animaux,id'],
            'date_abattage'     => ['required', 'date'],
            'poids_carcasse_kg' => ['nullable', 'numeric', 'min:0'],
            'rendement_pct'     => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes'             => ['nullable', 'string'],

            'lignes'               => ['nullable', 'array', 'min:1'],
            'lignes.*.categorie'   => ['required_with:lignes', 'string', $this->categorieProduitRule()],
            'lignes.*.poids_kg'    => ['required_with:lignes', 'numeric', 'min:0.001'],

            'stocks'                => ['nullable', 'array', 'min:1'],
            'stocks.*.produit_id'   => ['required_with:stocks', 'uuid', 'exists:produits,id'],
            'stocks.*.quantite'     => ['required_with:stocks', 'numeric', 'min:0'],
            'stocks.*.seuil_alerte' => ['nullable', 'numeric', 'min:0'],

            'attachment_ids'   => ['sometimes', 'array', 'max:3'],
            'attachment_ids.*' => ['uuid', 'exists:attachments,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $isFournisseur = $this->user()?->hasRole('fournisseur');
            $lignes        = $this->input('lignes', []);
            $stocks        = $this->input('stocks', []);

            if ($isFournisseur && empty($lignes)) {
                $validator->errors()->add(
                    'lignes',
                    'Au moins une ligne par catégorie est requise pour un abattage fournisseur.',
                );
            }

            if (! $isFournisseur && empty($lignes) && empty($stocks)) {
                $validator->errors()->add(
                    'lignes',
                    'Indiquez des lignes par catégorie ou des entrées de stock produit.',
                );
            }

            if ($isFournisseur && ! empty($stocks)) {
                $validator->errors()->add(
                    'stocks',
                    'Le stock produit n’est pas créé à l’abattage pour un fournisseur.',
                );
            }
        });
    }
}
