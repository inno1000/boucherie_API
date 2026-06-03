<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\Boucherie;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

describe('POST /api/v1/attachments', function () {
    it('enregistre un fichier audio (boucher)', function () {
        Storage::fake('local');
        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        Sanctum::actingAs($boucher);

        $file = UploadedFile::fake()->create('note.webm', 100, 'audio/webm');

        $this->post('/api/v1/attachments', ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('message', 'Fichier enregistré.')
            ->assertJsonStructure(['data' => ['id', 'mime_type', 'stream_url']]);

        $this->assertDatabaseCount('attachments', 1);
    });

    it('enregistre une image (fournisseur)', function () {
        Storage::fake('local');
        $fournisseur = \App\Models\Fournisseur::factory()->create();
        $user        = fournisseurUser($fournisseur);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('animal.jpg', 800, 600);

        $this->post('/api/v1/attachments', ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/jpeg');
    });

    it('retourne 422 si fichier absent', function () {
        $boucherie = Boucherie::factory()->create();
        Sanctum::actingAs(boucherUser($boucherie));

        $this->postJson('/api/v1/attachments', [])
            ->assertUnprocessable();
    });
});

describe('GET /api/v1/attachments/{id}/stream', function () {
    it('retourne le flux audio pour le propriétaire', function () {
        Storage::fake('local');
        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        Sanctum::actingAs($boucher);

        $path = 'attachments/'.$boucher->id.'/test.webm';
        Storage::disk('local')->put($path, 'fake-audio-content');

        $attachment = Attachment::create([
            'user_id'       => $boucher->id,
            'disk'          => 'local',
            'path'          => $path,
            'original_name' => 'note.webm',
            'mime_type'     => 'audio/webm',
            'size_bytes'    => 18,
        ]);

        $this->get("/api/v1/attachments/{$attachment->id}/stream")
            ->assertOk();
    });

    it('retourne 403 pour un autre utilisateur', function () {
        Storage::fake('local');
        $boucherie = Boucherie::factory()->create();
        $owner     = boucherUser($boucherie);
        $other     = boucherUser(Boucherie::factory()->create());

        $path = 'attachments/'.$owner->id.'/test.webm';
        Storage::disk('local')->put($path, 'content');

        $attachment = Attachment::create([
            'user_id'       => $owner->id,
            'disk'          => 'local',
            'path'          => $path,
            'original_name' => 'note.webm',
            'mime_type'     => 'audio/webm',
            'size_bytes'    => 7,
        ]);

        Sanctum::actingAs($other);

        $this->get("/api/v1/attachments/{$attachment->id}/stream")
            ->assertForbidden();
    });
});

describe('attachment_ids sur abattage', function () {
    it('lie les pièces jointes à l\'abattage créé', function () {
        Storage::fake('local');
        $boucherie = Boucherie::factory()->create();
        $boucher   = boucherUser($boucherie);
        Sanctum::actingAs($boucher);

        $path = 'attachments/'.$boucher->id.'/orphan.webm';
        Storage::disk('local')->put($path, 'audio');

        $attachment = Attachment::create([
            'user_id'       => $boucher->id,
            'disk'          => 'local',
            'path'          => $path,
            'original_name' => 'orphan.webm',
            'mime_type'     => 'audio/webm',
            'size_bytes'    => 5,
        ]);

        $animal  = \App\Models\Animal::factory()->create([
            'boucherie_id' => $boucherie->id,
            'statut'       => 'en_attente',
        ]);
        $produit = \App\Models\Produit::factory()->create(['boucherie_id' => $boucherie->id]);

        $this->postJson('/api/v1/abattages', [
            'animal_id'         => $animal->id,
            'date_abattage'     => '2026-05-10',
            'poids_carcasse_kg' => 200,
            'stocks'            => [
                ['produit_id' => $produit->id, 'quantite' => 200],
            ],
            'attachment_ids'    => [$attachment->id],
        ])->assertCreated()
          ->assertJsonPath('data.attachments.0.id', $attachment->id);

        $attachment->refresh();
        expect($attachment->attachable_type)->toBe(\App\Models\Abattage::class);
        expect($attachment->attachable_id)->not->toBeNull();
    });
});
