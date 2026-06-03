<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnimalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'espece'       => ['required', 'string', Rule::exists('enum_valeurs', 'valeur')->where('type', 'espece_animal')],
            'poids_vif_kg' => ['required', 'numeric', 'min:0'],
            'prix_achat'   => ['required', 'numeric', 'min:0'],
            'numero_tag'   => ['nullable', 'string', 'max:50'],
        ];
    }
}
