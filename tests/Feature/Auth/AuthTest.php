<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('POST /api/v1/auth/register', function () {
    it('inscrit un utilisateur avec des données valides', function () {
        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Jean Dupont',
            'email'                 => 'jean@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated()
          ->assertJsonStructure([
              'data'  => ['id', 'name', 'email', 'created_at'],
              'token',
              'message',
          ]);
    });

    it('retourne 422 si email déjà utilisé', function () {
        User::factory()->create(['email' => 'jean@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Jean',
            'email'                 => 'jean@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertUnprocessable()
          ->assertJsonStructure(['message', 'errors']);
    });

    it('retourne 422 si les champs obligatoires manquent', function () {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    });
});

describe('POST /api/v1/auth/login', function () {
    it('connecte un utilisateur avec des identifiants valides', function () {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertOk()
          ->assertJsonStructure(['data', 'token', 'message']);
    });

    it('retourne 401 avec des identifiants invalides', function () {
        $this->postJson('/api/v1/auth/login', [
            'email'    => 'inconnu@example.com',
            'password' => 'mauvais',
        ])->assertUnauthorized();
    });

    it('retourne 422 si les champs sont absents', function () {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    });
});

describe('PATCH /api/v1/auth/password', function () {
    it('met à jour le mot de passe sans rôle admin', function () {
        $user = User::factory()->create(['password' => 'ancien123']);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/auth/password', [
            'password'              => 'nouveau123',
            'password_confirmation' => 'nouveau123',
        ])->assertOk()
          ->assertJsonPath('message', 'Mot de passe mis à jour avec succès.');

        $user->refresh();
        expect(\Illuminate\Support\Facades\Hash::check('nouveau123', $user->password))->toBeTrue();
    });

    it('retourne 422 si confirmation incorrecte', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/auth/password', [
            'password'              => 'nouveau123',
            'password_confirmation' => 'autre',
        ])->assertUnprocessable();
    });

    it('retourne 401 sans authentification', function () {
        $this->patchJson('/api/v1/auth/password', [
            'password'              => 'nouveau123',
            'password_confirmation' => 'nouveau123',
        ])->assertUnauthorized();
    });
});

describe('POST /api/v1/auth/logout', function () {
    it('déconnecte un utilisateur authentifié', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Déconnexion réussie.');
    });

    it('retourne 401 sans token', function () {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    });
});

describe('PATCH /api/v1/auth/me', function () {
    it('met à jour le profil sans rôle admin', function () {
        $user = fournisseurUser();
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/auth/me', [
            'name'  => 'Fournisseur Modifié',
            'email' => 'fournisseur.modifie@example.com',
        ])->assertOk()
          ->assertJsonPath('message', 'Profil mis à jour avec succès.')
          ->assertJsonPath('data.name', 'Fournisseur Modifié')
          ->assertJsonPath('data.email', 'fournisseur.modifie@example.com');

        $user->refresh();
        expect($user->name)->toBe('Fournisseur Modifié')
            ->and($user->email)->toBe('fournisseur.modifie@example.com');
    });

    it('refuse un e-mail déjà utilisé', function () {
        $existing = User::factory()->create(['email' => 'pris@example.com']);
        Sanctum::actingAs(fournisseurUser());

        $this->patchJson('/api/v1/auth/me', [
            'email' => $existing->email,
        ])->assertUnprocessable();
    });

    it('retourne 401 sans authentification', function () {
        $this->patchJson('/api/v1/auth/me', ['name' => 'Test'])
            ->assertUnauthorized();
    });
});

describe('GET /api/v1/auth/me', function () {
    it('retourne le profil de l\'utilisateur connecté', function () {
        Sanctum::actingAs($user = User::factory()->create());

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissing(['password']);
    });

    it('retourne 401 sans token', function () {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    });
});
