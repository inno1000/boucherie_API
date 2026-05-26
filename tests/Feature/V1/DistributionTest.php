<?php

declare(strict_types=1);

use App\Models\Abattage;
use App\Models\Animal;
use App\Models\Boucherie;
use App\Models\Distribution;
use App\Models\DistributionLigne;
use App\Models\EnumValeur;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\StockCategorie;
use Laravel\Sanctum\Sanctum;

describe('GET /api/v1/distributions', function () {
    it('retourne les distributions destinées à la boucherie (boucher)', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        Distribution::factory()->count(2)->create(['boucherie_id' => $boucherie->id]);

        $this->getJson('/api/v1/distributions')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('retourne les distributions du fournisseur connecté', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        Sanctum::actingAs($user);

        Distribution::factory()->count(2)->create(['fournisseur_user_id' => $user->id]);

        $this->getJson('/api/v1/distributions')
            ->assertOk()
            ->assertJsonStructure(['data']);
    });

    it('retourne 401 sans authentification', function () {
        $this->getJson('/api/v1/distributions')
            ->assertUnauthorized();
    });
});

describe('POST /api/v1/distributions', function () {
    it('crée une distribution par catégories sans produit (fournisseur)', function () {
        EnumValeur::firstOrCreate(
            ['type' => 'categorie_produit', 'valeur' => 'viande_rouge'],
            ['libelle' => 'Viande rouge', 'systeme' => false, 'ordre' => 1],
        );

        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        $boucherie   = Boucherie::factory()->create();
        $fournisseur->boucheries()->attach($boucherie->id);

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

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/distributions', [
            'abattage_id'  => $abattage->id,
            'boucherie_id' => $boucherie->id,
            'lignes'       => [
                ['categorie' => 'viande_rouge', 'poids_kg' => 45.5, 'prix_par_kg' => 2500],
            ],
        ])->assertCreated()
          ->assertJsonPath('data.quantite', '45.500')
          ->assertJsonPath('data.produit_id', null)
          ->assertJsonCount(1, 'data.lignes');

        expect(DistributionLigne::count())->toBe(1);
    });

    it('crée une distribution (fournisseur)', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        Sanctum::actingAs($user);

        $boucherie = Boucherie::factory()->create();
        $produit   = Produit::factory()->create(['boucherie_id' => $boucherie->id]);
        $abattage  = Abattage::factory()->create([
            'boucherie_id' => $boucherie->id,
            'user_id'      => $user->id,
        ]);

        $this->postJson('/api/v1/distributions', [
            'abattage_id'  => $abattage->id,
            'boucherie_id' => $boucherie->id,
            'produit_id'   => $produit->id,
            'quantite'     => 45.5,
            'notes'        => 'Lot de côtes',
        ])->assertCreated()
          ->assertJsonPath('message', 'Distribution créée avec succès.')
          ->assertJsonPath('data.statut', 'en_attente');
    });

    it('crée une distribution (admin)', function () {
        Sanctum::actingAs(adminUser());

        $boucherie = Boucherie::factory()->create();
        $produit   = Produit::factory()->create(['boucherie_id' => $boucherie->id]);
        $boucher   = boucherUser($boucherie);
        $abattage  = Abattage::factory()->create([
            'boucherie_id' => $boucherie->id,
            'user_id'      => $boucher->id,
        ]);

        $this->postJson('/api/v1/distributions', [
            'abattage_id'  => $abattage->id,
            'boucherie_id' => $boucherie->id,
            'produit_id'   => $produit->id,
            'quantite'     => 20.0,
        ])->assertCreated();
    });

    it('retourne 422 si les champs obligatoires sont absents', function () {
        $fournisseur = Fournisseur::factory()->create();
        Sanctum::actingAs(fournisseurUser($fournisseur));

        $this->postJson('/api/v1/distributions', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    });

    it('retourne 403 pour un boucher', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->postJson('/api/v1/distributions', [
            'abattage_id'  => '00000000-0000-0000-0000-000000000000',
            'boucherie_id' => $boucherie->id,
            'produit_id'   => '00000000-0000-0000-0000-000000000000',
            'quantite'     => 10,
        ])->assertForbidden();
    });

    it('retourne 401 sans authentification', function () {
        $this->postJson('/api/v1/distributions', [])
            ->assertUnauthorized();
    });
});

describe('GET /api/v1/distributions/{id}', function () {
    it('retourne le détail d\'une distribution (boucher propriétaire)', function () {
        $boucherie    = Boucherie::factory()->create();
        $distribution = Distribution::factory()->create(['boucherie_id' => $boucherie->id]);
        Sanctum::actingAs(boucherUser($boucherie));

        $this->getJson("/api/v1/distributions/{$distribution->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $distribution->id);
    });

    it('retourne 404 si introuvable', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->getJson('/api/v1/distributions/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    });
});

describe('PATCH /api/v1/distributions/{id}/annuler', function () {
    it('annule une distribution en attente (fournisseur propriétaire)', function () {
        $fournisseur  = Fournisseur::factory()->create();
        $user         = fournisseurUser($fournisseur);
        $distribution = Distribution::factory()->create([
            'fournisseur_user_id' => $user->id,
            'statut'              => 'en_attente',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/distributions/{$distribution->id}/annuler")
            ->assertOk()
            ->assertJsonPath('message', 'Distribution annulée.');
    });

    it('retourne 401 sans authentification', function () {
        $distribution = Distribution::factory()->create();

        $this->patchJson("/api/v1/distributions/{$distribution->id}/annuler")
            ->assertUnauthorized();
    });
});
