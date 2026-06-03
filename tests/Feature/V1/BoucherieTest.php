<?php

declare(strict_types=1);

use App\Models\Boucherie;
use App\Models\Fournisseur;
use Laravel\Sanctum\Sanctum;

// ── GET /api/v1/boucheries ─────────────────────────────────────────────────

describe('GET /api/v1/boucheries', function () {
    it('retourne la liste paginée pour un admin', function () {
        Sanctum::actingAs(adminUser());
        Boucherie::factory()->count(3)->create();

        $this->getJson('/api/v1/boucheries')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('retourne la liste pour un boucher', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->getJson('/api/v1/boucheries')
            ->assertOk()
            ->assertJsonStructure(['data']);
    });

    it('retourne uniquement les boucheries desservies pour un fournisseur', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        $served      = Boucherie::factory()->create(['nom' => 'Boucherie desservie']);
        $other       = Boucherie::factory()->create(['nom' => 'Boucherie autre']);
        $fournisseur->boucheries()->attach($served->id);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/boucheries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $served->id);

        $ids = collect($response->json('data'))->pluck('id')->all();
        expect($ids)->toContain($served->id)->not->toContain($other->id);
    });

    it('retourne 401 sans authentification', function () {
        $this->getJson('/api/v1/boucheries')
            ->assertUnauthorized();
    });
});

// ── POST /api/v1/boucheries ────────────────────────────────────────────────

describe('POST /api/v1/boucheries', function () {
    it('crée une boucherie avec des données valides (admin)', function () {
        Sanctum::actingAs(adminUser());

        $this->postJson('/api/v1/boucheries', [
            'nom'       => 'Boucherie du Marché',
            'adresse'   => '12 rue du Marché',
            'ville'     => 'Dakar',
            'telephone' => '+221701234567',
        ])->assertCreated()
          ->assertJsonPath('data.nom', 'Boucherie du Marché')
          ->assertJsonPath('message', 'Boucherie créée avec succès.');
    });

    it('crée une boucherie (boucher)', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->postJson('/api/v1/boucheries', [
            'nom'     => 'Nouvelle Boucherie',
            'adresse' => '5 avenue centrale',
            'ville'   => 'Abidjan',
        ])->assertCreated();
    });

    it('retourne 403 pour un fournisseur', function () {
        Sanctum::actingAs(fournisseurUser());

        $this->postJson('/api/v1/boucheries', ['nom' => 'Test'])
            ->assertForbidden();
    });

    it('retourne 422 si le nom est absent', function () {
        Sanctum::actingAs(adminUser());

        $this->postJson('/api/v1/boucheries', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    });

    it('retourne 401 sans authentification', function () {
        $this->postJson('/api/v1/boucheries', ['nom' => 'Test'])
            ->assertUnauthorized();
    });
});

// ── GET /api/v1/boucheries/{id} ────────────────────────────────────────────

describe('GET /api/v1/boucheries/{id}', function () {
    it('retourne le détail d\'une boucherie', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(adminUser());

        $this->getJson("/api/v1/boucheries/{$boucherie->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $boucherie->id)
            ->assertJsonPath('data.nom', $boucherie->nom);
    });

    it('retourne 404 si la boucherie est introuvable', function () {
        Sanctum::actingAs(adminUser());

        $this->getJson('/api/v1/boucheries/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    });

    it('retourne 401 sans authentification', function () {
        $boucherie = Boucherie::factory()->create();

        $this->getJson("/api/v1/boucheries/{$boucherie->id}")
            ->assertUnauthorized();
    });
});

// ── PUT /api/v1/boucheries/{id} ────────────────────────────────────────────

describe('PUT /api/v1/boucheries/{id}', function () {
    it('met à jour une boucherie (admin)', function () {
        $boucherie = Boucherie::factory()->create(['nom' => 'Ancien Nom']);
        Sanctum::actingAs(adminUser());

        $this->putJson("/api/v1/boucheries/{$boucherie->id}", ['nom' => 'Nouveau Nom'])
            ->assertOk()
            ->assertJsonPath('data.nom', 'Nouveau Nom')
            ->assertJsonPath('message', 'Boucherie mise à jour avec succès.');
    });

    it('retourne 403 pour un fournisseur', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(fournisseurUser());

        $this->putJson("/api/v1/boucheries/{$boucherie->id}", ['nom' => 'Test'])
            ->assertForbidden();
    });

    it('retourne 404 si introuvable', function () {
        Sanctum::actingAs(adminUser());

        $this->putJson('/api/v1/boucheries/00000000-0000-0000-0000-000000000000', ['nom' => 'Test'])
            ->assertNotFound();
    });
});

// ── DELETE /api/v1/boucheries/{id} ─────────────────────────────────────────

describe('DELETE /api/v1/boucheries/{id}', function () {
    it('supprime une boucherie (admin)', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(adminUser());

        $this->deleteJson("/api/v1/boucheries/{$boucherie->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('boucheries', ['id' => $boucherie->id]);
    });

    it('retourne 403 pour un fournisseur', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(fournisseurUser());

        $this->deleteJson("/api/v1/boucheries/{$boucherie->id}")
            ->assertForbidden();
    });

    it('retourne 401 sans authentification', function () {
        $boucherie = Boucherie::factory()->create();

        $this->deleteJson("/api/v1/boucheries/{$boucherie->id}")
            ->assertUnauthorized();
    });
});
