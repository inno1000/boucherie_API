<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesCategorieProduit
{
    protected function categorieProduitRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('enum_valeurs', 'valeur')->where('type', 'categorie_produit');
    }
}
