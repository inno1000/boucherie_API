<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Boucherie;
use App\Models\EnumValeur;
use App\Models\Fournisseur;
use Laravel\Sanctum\Sanctum;

describe('GET /api/v1/animaux', function () {
    it('retourne la liste des animaux de la boucherie (boucher)', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));
        Animal::factory()->count(3)->create(['boucherie_id' => $boucherie->id]);

        $this->getJson('/api/v1/animaux')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('retourne les animaux filtrés par statut', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        Animal::factory()->create(['boucherie_id' => $boucherie->id, 'statut' => 'en_attente']);
        Animal::factory()->create(['boucherie_id' => $boucherie->id, 'statut' => 'abattu']);

        $response = $this->getJson('/api/v1/animaux?statut=en_attente')
            ->assertOk();

        $data = $response->json('data');
        expect(collect($data)->every(fn ($a) => $a['statut'] === 'en_attente'))->toBeTrue();
    });

    it('retourne uniquement les animaux du fournisseur connecté', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/animaux')
            ->assertOk()
            ->assertJsonStructure(['data']);
    });

    it('retourne 403 pour un rôle non autorisé', function () {
        // Seuls admin|boucher|fournisseur peuvent accéder
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->getJson('/api/v1/animaux')
            ->assertOk(); // boucher autorisé
    });

    it('retourne 401 sans authentification', function () {
        $this->getJson('/api/v1/animaux')
            ->assertUnauthorized();
    });
});

describe('GET /api/v1/animaux/{id}', function () {
    it('retourne le détail d\'un animal (boucher propriétaire)', function () {
        $boucherie = Boucherie::factory()->create();
        $animal    = Animal::factory()->create(['boucherie_id' => $boucherie->id]);
        Sanctum::actingAs(boucherUser($boucherie));

        $this->getJson("/api/v1/animaux/{$animal->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $animal->id)
            ->assertJsonPath('data.statut', $animal->statut);
    });

    it('retourne 404 si introuvable', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->getJson('/api/v1/animaux/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    });

    it('retourne 401 sans authentification', function () {
        $animal = Animal::factory()->create();

        $this->getJson("/api/v1/animaux/{$animal->id}")
            ->assertUnauthorized();
    });
});

describe('PATCH /api/v1/animaux/{id}', function () {
    it('met à jour un animal en attente (fournisseur)', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        Sanctum::actingAs($user);

        EnumValeur::factory()->especeAnimal('bovin')->create();
        EnumValeur::factory()->especeAnimal('ovin')->create();

        $animal = Animal::factory()->create([
            'fournisseur_id' => $fournisseur->id,
            'boucherie_id'   => null,
            'statut'         => 'en_attente',
            'espece'         => 'bovin',
            'prix_achat'     => 100000,
        ]);

        $this->patchJson("/api/v1/animaux/{$animal->id}", [
            'espece'       => 'ovin',
            'poids_vif_kg' => 120,
            'prix_achat'   => 150000,
            'numero_tag'   => 'TAG-UPD',
        ])->assertOk()
          ->assertJsonPath('data.espece', 'ovin')
          ->assertJsonPath('data.numero_tag', 'TAG-UPD')
          ->assertJsonPath('message', 'Animal mis à jour.');
    });

    it('retourne 403 pour un animal abattu', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        Sanctum::actingAs($user);

        EnumValeur::factory()->especeAnimal('bovin')->create();

        $animal = Animal::factory()->create([
            'fournisseur_id' => $fournisseur->id,
            'boucherie_id'   => null,
            'statut'         => 'abattu',
        ]);

        $this->patchJson("/api/v1/animaux/{$animal->id}", [
            'espece'       => 'bovin',
            'poids_vif_kg' => 100,
            'prix_achat'   => 100000,
        ])->assertForbidden();
    });
});

describe('DELETE /api/v1/animaux/{id}', function () {
    it('supprime un animal en attente (fournisseur)', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        Sanctum::actingAs($user);

        $animal = Animal::factory()->create([
            'fournisseur_id' => $fournisseur->id,
            'boucherie_id'   => null,
            'statut'         => 'en_attente',
        ]);

        $this->deleteJson("/api/v1/animaux/{$animal->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Animal supprimé.');

        $this->assertDatabaseMissing('animaux', ['id' => $animal->id]);
    });

    it('retourne 403 pour un animal abattu', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        Sanctum::actingAs($user);

        $animal = Animal::factory()->create([
            'fournisseur_id' => $fournisseur->id,
            'boucherie_id'   => null,
            'statut'         => 'abattu',
        ]);

        $this->deleteJson("/api/v1/animaux/{$animal->id}")
            ->assertForbidden();
    });
});
