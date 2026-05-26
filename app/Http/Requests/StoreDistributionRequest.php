<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCategorieProduit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDistributionRequest extends FormRequest
{
    use ValidatesCategorieProduit;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'abattage_id'  => ['required', 'uuid', 'exists:abattages,id'],
            'boucherie_id' => ['required', 'uuid', 'exists:boucheries,id'],
            'produit_id'   => ['nullable', 'uuid', 'exists:produits,id'],
            'quantite'     => ['nullable', 'numeric', 'min:0.001'],
            'notes'        => ['nullable', 'string', 'max:1000'],

            'lignes'               => ['nullable', 'array', 'min:1'],
            'lignes.*.categorie'   => ['required_with:lignes', 'string', $this->categorieProduitRule()],
            'lignes.*.poids_kg'    => ['required_with:lignes', 'numeric', 'min:0.001'],
            'lignes.*.prix_par_kg' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $lignes    = $this->input('lignes', []);
            $produitId = $this->input('produit_id');
            $quantite  = $this->input('quantite');

            if (empty($lignes) && (empty($produitId) || $quantite === null)) {
                $validator->errors()->add(
                    'lignes',
                    'Indiquez des lignes par catégorie ou un produit avec une quantité.',
                );
            }
        });
    }
}
