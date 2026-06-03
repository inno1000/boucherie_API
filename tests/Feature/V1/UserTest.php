<?php

declare(strict_types=1);

use App\Models\Boucherie;
use App\Models\Fournisseur;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('GET /api/v1/users', function () {
    it('retourne la liste des utilisateurs (admin)', function () {
        Sanctum::actingAs(adminUser());
        User::factory()->count(3)->create();

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('retourne 403 pour un boucher', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->getJson('/api/v1/users')
            ->assertForbidden();
    });

    it('retourne 403 pour un fournisseur', function () {
        Sanctum::actingAs(fournisseurUser());

        $this->getJson('/api/v1/users')
            ->assertForbidden();
    });

    it('retourne 401 sans authentification', function () {
        $this->getJson('/api/v1/users')
            ->assertUnauthorized();
    });

    it('inclut les fournisseurs même si l\'admin est rattaché à une boucherie', function () {
        $boucherie = Boucherie::factory()->create();
        $admin     = adminUser($boucherie->id);
        $supplier  = fournisseurUser();
        Fournisseur::factory()->create(['user_id' => $supplier->id]);
        $localStaff = boucherUser($boucherie);

        Sanctum::actingAs($admin);

        $ids = collect($this->getJson('/api/v1/users')->assertOk()->json('data'))
            ->pluck('id');

        expect($ids)->toContain($supplier->id)
            ->and($ids)->toContain($localStaff->id);
    });
});

describe('POST /api/v1/users', function () {
    it('crée un utilisateur boucher (admin)', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(adminUser());

        $this->postJson('/api/v1/users', [
            'name'        => 'Alice Boucher',
            'email'       => 'alice@boucherie.com',
            'password'    => 'motdepasse8',
            'role'        => 'boucher',
            'boucherie_id' => $boucherie->id,
        ])->assertCreated()
          ->assertJsonPath('data.name', 'Alice Boucher')
          ->assertJsonPath('message', 'Utilisateur créé avec succès.');
    });

    it('crée un utilisateur fournisseur (admin)', function () {
        Sanctum::actingAs(adminUser());

        $this->postJson('/api/v1/users', [
            'name'     => 'Jean Fournisseur',
            'email'    => 'jean@fournisseur.com',
            'password' => 'motdepasse8',
            'role'     => 'fournisseur',
            'fournisseur' => [
                'nom'     => 'Élevage Koné',
                'contact' => 'Jean Koné',
            ],
        ])->assertCreated()
          ->assertJsonStructure(['data' => ['entite_fournisseur']]);
    });

    it('retourne 422 si email déjà utilisé', function () {
        $boucherie = Boucherie::factory()->create();
        $existing  = User::factory()->create(['email' => 'taken@example.com']);
        Sanctum::actingAs(adminUser());

        $this->postJson('/api/v1/users', [
            'name'        => 'Test',
            'email'       => 'taken@example.com',
            'password'    => 'motdepasse8',
            'role'        => 'boucher',
            'boucherie_id' => $boucherie->id,
        ])->assertUnprocessable()
          ->assertJsonStructure(['message', 'errors']);
    });

    it('retourne 422 si boucherie_id manque pour un boucher', function () {
        Sanctum::actingAs(adminUser());

        $this->postJson('/api/v1/users', [
            'name'     => 'Test',
            'email'    => 'test2@example.com',
            'password' => 'motdepasse8',
            'role'     => 'boucher',
        ])->assertUnprocessable();
    });

    it('retourne 403 pour un boucher', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->postJson('/api/v1/users', [
            'name'  => 'Test',
            'email' => 'test@x.com',
        ])->assertForbidden();
    });
});

describe('GET /api/v1/users/{id}', function () {
    it('retourne le détail d\'un utilisateur (admin)', function () {
        $user = User::factory()->create();
        Sanctum::actingAs(adminUser());

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    });

    it('retourne 404 si introuvable', function () {
        Sanctum::actingAs(adminUser());

        $this->getJson('/api/v1/users/99999')
            ->assertNotFound();
    });
});

describe('PUT /api/v1/users/{id}', function () {
    it('met à jour un utilisateur (admin)', function () {
        $user = User::factory()->create(['name' => 'Ancien Nom']);
        Sanctum::actingAs(adminUser());

        $this->putJson("/api/v1/users/{$user->id}", ['name' => 'Nouveau Nom'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nouveau Nom')
            ->assertJsonPath('message', 'Utilisateur mis à jour avec succès.');
    });
});

describe('PATCH /api/v1/users/{id} — boucherie_ids fournisseur', function () {
    it('synchronise les boucheries desservies pour un fournisseur', function () {
        $b1 = Boucherie::factory()->create();
        $b2 = Boucherie::factory()->create();
        $user = fournisseurUser();
        Fournisseur::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs(adminUser());

        $this->patchJson("/api/v1/users/{$user->id}", [
            'boucherie_ids' => [$b1->id, $b2->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.boucherie_ids', [$b1->id, $b2->id]);

        $fournisseur = $user->fresh()->fournisseur;
        expect($fournisseur)->not->toBeNull();
        expect($fournisseur->boucheries()->pluck('boucheries.id')->all())
            ->toEqualCanonicalizing([$b1->id, $b2->id]);
    });

    it('refuse d\'attribuer une boucherie déjà liée à un autre fournisseur', function () {
        $b1   = Boucherie::factory()->create();
        $f1   = fournisseurUser();
        $f2   = fournisseurUser();
        Fournisseur::factory()->create(['user_id' => $f1->id]);
        $ent2 = Fournisseur::factory()->create(['user_id' => $f2->id]);

        $ent2->boucheries()->attach($b1->id);

        Sanctum::actingAs(adminUser());

        $this->patchJson("/api/v1/users/{$f1->id}", [
            'boucherie_ids' => [$b1->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['boucherie_ids']);
    });
});

describe('DELETE /api/v1/users/{id}', function () {
    it('supprime un utilisateur (admin)', function () {
        $user = User::factory()->create();
        Sanctum::actingAs(adminUser());

        $this->deleteJson("/api/v1/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    });

    it('retourne 403 pour un boucher', function () {
        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        $target    = User::factory()->create();
        Sanctum::actingAs($boucher);

        $this->deleteJson("/api/v1/users/{$target->id}")
            ->assertForbidden();
    });

    it('retourne 401 sans authentification', function () {
        $user = User::factory()->create();

        $this->deleteJson("/api/v1/users/{$user->id}")
            ->assertUnauthorized();
    });
});
