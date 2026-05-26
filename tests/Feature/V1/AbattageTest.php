<?php

declare(strict_types=1);

use App\Models\Abattage;
use App\Models\Animal;
use App\Models\Boucherie;
use App\Models\EnumValeur;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\StockCategorie;
use Laravel\Sanctum\Sanctum;

describe('GET /api/v1/abattages', function () {
    it('retourne la liste des abattages (boucher)', function () {
        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        Sanctum::actingAs($boucher);

        Abattage::factory()->count(2)->create([
            'boucherie_id' => $boucherie->id,
            'user_id'      => $boucher->id,
        ]);

        $this->getJson('/api/v1/abattages')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('retourne 403 si non autorisé (rôle manquant)', function () {
        // Un utilisateur sans rôle ne peut pas accéder
        $user = \App\Models\User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/abattages')
            ->assertForbidden();
    });

    it('retourne 401 sans authentification', function () {
        $this->getJson('/api/v1/abattages')
            ->assertUnauthorized();
    });
});

describe('POST /api/v1/abattages', function () {
    it('enregistre un abattage et crée le stock (boucher)', function () {
        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        $animal    = Animal::factory()->create([
            'boucherie_id' => $boucherie->id,
            'statut'       => 'en_attente',
        ]);
        $produit = Produit::factory()->create(['boucherie_id' => $boucherie->id]);

        Sanctum::actingAs($boucher);

        $this->postJson('/api/v1/abattages', [
            'animal_id'         => $animal->id,
            'date_abattage'     => '2026-05-10',
            'poids_carcasse_kg' => 210.5,
            'rendement_pct'     => 60.0,
            'stocks'            => [
                [
                    'produit_id'   => $produit->id,
                    'quantite'     => 210.5,
                    'seuil_alerte' => 10,
                ],
            ],
        ])->assertCreated()
          ->assertJsonPath('message', 'Abattage enregistré avec succès.')
          ->assertJsonStructure(['data' => ['stocks']]);
    });

    it('retourne 422 si animal_id est absent', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->postJson('/api/v1/abattages', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    });

    it('enregistre un abattage par catégories sans stock produit (boucher)', function () {
        EnumValeur::firstOrCreate(
            ['type' => 'categorie_produit', 'valeur' => 'viande_rouge'],
            ['libelle' => 'Viande rouge', 'systeme' => false, 'ordre' => 1],
        );

        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        $animal    = Animal::factory()->create([
            'boucherie_id' => $boucherie->id,
            'statut'       => 'en_attente',
        ]);
        Sanctum::actingAs($boucher);

        $this->postJson('/api/v1/abattages', [
            'animal_id'     => $animal->id,
            'date_abattage' => '2026-05-10',
            'lignes'        => [
                ['categorie' => 'viande_rouge', 'poids_kg' => 80],
            ],
        ])->assertCreated()
          ->assertJsonPath('data.poids_carcasse_kg', '80.00');

        $stockCat = StockCategorie::where('boucherie_id', $boucherie->id)
            ->where('categorie', 'viande_rouge')
            ->first();

        expect($stockCat)->not->toBeNull()
            ->and((float) $stockCat->poids_kg_disponible)->toBe(80.0);
    });

    it('enregistre un abattage fournisseur avec lignes par catégorie', function () {
        EnumValeur::firstOrCreate(
            ['type' => 'categorie_produit', 'valeur' => 'abats'],
            ['libelle' => 'Abats', 'systeme' => false, 'ordre' => 1],
        );

        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        $animal      = Animal::factory()->create([
            'boucherie_id'   => null,
            'fournisseur_id' => $fournisseur->id,
            'statut'         => 'en_attente',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/abattages', [
            'animal_id'     => $animal->id,
            'date_abattage' => '2026-05-10',
            'lignes'        => [
                ['categorie' => 'abats', 'poids_kg' => 12.5],
            ],
        ])->assertCreated()
          ->assertJsonPath('data.poids_carcasse_kg', '12.50')
          ->assertJsonCount(1, 'data.lignes');
    });

    it('retourne 422 si stocks manque pour un boucher sans lignes', function () {
        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        $animal    = Animal::factory()->create([
            'boucherie_id' => $boucherie->id,
            'statut'       => 'en_attente',
        ]);
        Sanctum::actingAs($boucher);

        $this->postJson('/api/v1/abattages', [
            'animal_id'         => $animal->id,
            'date_abattage'     => '2026-05-10',
            'poids_carcasse_kg' => 200,
        ])->assertUnprocessable();
    });

    it('retourne 401 sans authentification', function () {
        $this->postJson('/api/v1/abattages', [])
            ->assertUnauthorized();
    });
});

describe('GET /api/v1/abattages/{id}', function () {
    it('retourne le détail d\'un abattage (boucher propriétaire)', function () {
        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        $abattage  = Abattage::factory()->create([
            'boucherie_id' => $boucherie->id,
            'user_id'      => $boucher->id,
        ]);
        Sanctum::actingAs($boucher);

        $this->getJson("/api/v1/abattages/{$abattage->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $abattage->id);
    });

    it('retourne 404 si introuvable', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->getJson('/api/v1/abattages/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    });
});
