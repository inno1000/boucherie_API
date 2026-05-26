<?php

declare(strict_types=1);

use App\Models\Abattage;
use App\Models\Animal;
use App\Models\Boucherie;
use App\Models\Distribution;
use App\Models\DistributionLigne;
use App\Models\EnumValeur;
use App\Models\Fournisseur;
use App\Models\StockCategorie;
use Laravel\Sanctum\Sanctum;

describe('POST /api/v1/receptions', function () {
    it('réceptionne une distribution par catégories et alimente stocks_categories', function () {
        EnumValeur::firstOrCreate(
            ['type' => 'categorie_produit', 'valeur' => 'abats'],
            ['libelle' => 'Abats', 'systeme' => false, 'ordre' => 1],
        );

        $boucherie   = Boucherie::factory()->create();
        $boucher     = boucherUser($boucherie);
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);

        $animal = Animal::factory()->create([
            'boucherie_id'   => null,
            'fournisseur_id' => $fournisseur->id,
            'statut'         => 'abattu',
        ]);
        $abattage = Abattage::factory()->create([
            'animal_id'    => $animal->id,
            'boucherie_id' => null,
            'user_id'      => $user->id,
        ]);

        $distribution = Distribution::factory()->create([
            'abattage_id'         => $abattage->id,
            'fournisseur_user_id' => $user->id,
            'boucherie_id'        => $boucherie->id,
            'produit_id'          => null,
            'quantite'            => 20,
            'statut'              => 'en_attente',
        ]);

        DistributionLigne::create([
            'distribution_id' => $distribution->id,
            'categorie'       => 'abats',
            'poids_kg'        => 20,
            'prix_par_kg'     => 1500,
        ]);

        Sanctum::actingAs($boucher);

        $this->postJson('/api/v1/receptions', [
            'distribution_id' => $distribution->id,
            'quantite_recue'  => 20,
            'date_reception'  => '2026-05-10',
        ])->assertCreated();

        $stockCat = StockCategorie::where('boucherie_id', $boucherie->id)
            ->where('categorie', 'abats')
            ->first();

        expect($stockCat)->not->toBeNull()
            ->and((float) $stockCat->poids_kg_disponible)->toBe(20.0);
    });
});
