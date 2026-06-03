<?php

declare(strict_types=1);

use App\Models\Boucherie;
use App\Models\Fournisseur;
use App\Models\User;
use App\Models\Versement;
use Laravel\Sanctum\Sanctum;

describe('GET /api/v1/versements', function () {
    it('retourne les versements de la boucherie (boucher)', function () {
        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        Sanctum::actingAs($boucher);

        Versement::factory()->count(2)->create(['boucherie_id' => $boucherie->id]);

        $this->getJson('/api/v1/versements')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    });

    it('retourne les versements du fournisseur connecté', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        Sanctum::actingAs($user);

        Versement::factory()->count(2)->create(['fournisseur_user_id' => $user->id]);

        $this->getJson('/api/v1/versements')
            ->assertOk();
    });

    it('retourne 401 sans authentification', function () {
        $this->getJson('/api/v1/versements')
            ->assertUnauthorized();
    });
});

describe('POST /api/v1/versements', function () {
    it('crée un versement (boucher)', function () {
        $boucherie    = Boucherie::factory()->create();
        $boucher      = boucherUser($boucherie);
        $fournisseur  = Fournisseur::factory()->create();
        $fourn_user   = fournisseurUser($fournisseur);
        Sanctum::actingAs($boucher);

        $this->postJson('/api/v1/versements', [
            'fournisseur_user_id' => $fourn_user->id,
            'montant'             => 150000,
            'mode_paiement'       => 'mobile_money',
            'date_versement'      => '2026-05-15',
            'reference'           => 'TXN-2026-001',
        ])->assertCreated()
          ->assertJsonPath('message', 'Versement enregistré avec succès.')
          ->assertJsonPath('data.statut', 'en_attente');
    });

    it('retourne 422 si les champs obligatoires sont absents', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->postJson('/api/v1/versements', [])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);
    });

    it('retourne 403 pour un fournisseur', function () {
        $fournisseur = Fournisseur::factory()->create();
        Sanctum::actingAs(fournisseurUser($fournisseur));

        $this->postJson('/api/v1/versements', [
            'fournisseur_user_id' => 1,
            'montant'             => 100,
            'mode_paiement'       => 'cash',
            'date_versement'      => '2026-05-15',
        ])->assertForbidden();
    });

    it('retourne 401 sans authentification', function () {
        $this->postJson('/api/v1/versements', [])
            ->assertUnauthorized();
    });
});

describe('GET /api/v1/versements/{id}', function () {
    it('retourne le détail d\'un versement (admin)', function () {
        $versement = Versement::factory()->create();
        Sanctum::actingAs(adminUser());

        $this->getJson("/api/v1/versements/{$versement->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $versement->id);
    });

    it('retourne 404 si introuvable', function () {
        Sanctum::actingAs(adminUser());

        $this->getJson('/api/v1/versements/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    });
});

describe('PATCH /api/v1/versements/{id}/valider', function () {
    it('valide un versement (fournisseur propriétaire)', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        $versement   = Versement::factory()->create([
            'fournisseur_user_id' => $user->id,
            'statut'              => 'en_attente',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/versements/{$versement->id}/valider")
            ->assertOk()
            ->assertJsonPath('data.statut', 'valide')
            ->assertJsonPath('message', 'Versement validé avec succès.');
    });

    it('retourne 403 pour un boucher', function () {
        $boucherie = Boucherie::factory()->create();
        $versement = Versement::factory()->create(['boucherie_id' => $boucherie->id]);
        Sanctum::actingAs(boucherUser($boucherie));

        $this->patchJson("/api/v1/versements/{$versement->id}/valider")
            ->assertForbidden();
    });
});

describe('PATCH /api/v1/versements/{id}/rejeter', function () {
    it('rejette un versement (fournisseur propriétaire)', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        $versement   = Versement::factory()->create([
            'fournisseur_user_id' => $user->id,
            'statut'              => 'en_attente',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/versements/{$versement->id}/rejeter", [
            'motif_rejet' => 'Montant insuffisant',
        ])->assertOk()
          ->assertJsonPath('data.statut', 'rejete')
          ->assertJsonPath('data.motif_rejet', 'Montant insuffisant')
          ->assertJsonPath('message', 'Versement rejeté.');
    });

    it('exige un motif ou une pièce jointe pour rejeter', function () {
        $fournisseur = Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        $versement   = Versement::factory()->create([
            'fournisseur_user_id' => $user->id,
            'statut'              => 'en_attente',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/versements/{$versement->id}/rejeter", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['motif_rejet']);
    });

    it('retourne 401 sans authentification', function () {
        $versement = Versement::factory()->create();

        $this->patchJson("/api/v1/versements/{$versement->id}/valider")
            ->assertUnauthorized();
    });
});
