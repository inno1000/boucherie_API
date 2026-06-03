<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Boucherie;
use App\Models\Distribution;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\User;
use App\Models\Versement;
use Laravel\Sanctum\Sanctum;

describe('GET /api/v1/dashboard/today', function () {
    it('retourne 401 sans authentification', function () {
        $this->getJson('/api/v1/dashboard/today')
            ->assertUnauthorized();
    });

    it('retourne le hub boucher avec tâches stock et réception', function () {
        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        $produit   = Produit::factory()->create(['boucherie_id' => $boucherie->id]);

        Stock::factory()->create([
            'boucherie_id'  => $boucherie->id,
            'produit_id'    => $produit->id,
            'quantite'      => 1,
            'seuil_alerte'  => 10,
        ]);

        Distribution::factory()->create([
            'boucherie_id'        => $boucherie->id,
            'fournisseur_user_id' => fournisseurUser()->id,
            'produit_id'          => $produit->id,
            'statut'              => 'en_attente',
        ]);

        Sanctum::actingAs($boucher);

        $response = $this->getJson('/api/v1/dashboard/today')
            ->assertOk()
            ->assertJsonPath('data.role', 'butcher')
            ->assertJsonStructure([
                'data' => ['role', 'date', 'summary', 'tasks', 'recent'],
            ]);

        $tasks = collect($response->json('data.tasks'))->pluck('id');
        expect($tasks)->toContain('stock_alerts', 'receptions_pending');
    });

    it('retourne le hub fournisseur avec animaux et versements en attente', function () {
        $fournisseur = Fournisseur::factory()->create();
        $supplier    = fournisseurUser($fournisseur);

        Animal::factory()->create([
            'fournisseur_id' => $fournisseur->id,
            'statut'         => 'en_attente',
        ]);

        Versement::factory()->create([
            'fournisseur_user_id' => $supplier->id,
            'statut'              => 'en_attente',
        ]);

        Sanctum::actingAs($supplier);

        $response = $this->getJson('/api/v1/dashboard/today')
            ->assertOk()
            ->assertJsonPath('data.role', 'supplier');

        $tasks = collect($response->json('data.tasks'))->pluck('id');
        expect($tasks)->toContain('animals_pending', 'versements_to_validate');
    });

    it('retourne le hub admin', function () {
        Sanctum::actingAs(adminUser());

        $this->getJson('/api/v1/dashboard/today')
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonStructure(['data' => ['summary' => ['users_total', 'butcheries_total']]]);
    });

    it('retourne 403 pour un utilisateur sans boucherie ni rôle métier', function () {
        $user = User::factory()->create(['boucherie_id' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard/today')
            ->assertForbidden();
    });
});
