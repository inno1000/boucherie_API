<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCategorieProduit;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceptionRequest extends FormRequest
{
    use ValidatesCategorieProduit;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'distribution_id' => ['required', 'uuid', 'exists:distributions,id'],
            'quantite_recue'  => ['required', 'numeric', 'min:0.001'],
            'date_reception'  => ['required', 'date'],
            'notes'           => ['nullable', 'string', 'max:1000'],

            'lignes'                    => ['nullable', 'array', 'min:1'],
            'lignes.*.categorie'        => ['required_with:lignes', 'string', $this->categorieProduitRule()],
            'lignes.*.poids_kg_attendu' => ['nullable', 'numeric', 'min:0'],
            'lignes.*.poids_kg_recu'    => ['required_with:lignes', 'numeric', 'min:0.001'],

            'attachment_ids'   => ['sometimes', 'array', 'max:3'],
            'attachment_ids.*' => ['uuid', 'exists:attachments,id'],
        ];
    }
}
