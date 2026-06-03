<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Boucherie;
use App\Models\Fournisseur;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $boucherie = Boucherie::firstOrCreate(
            ['nom' => 'Boucherie Test'],
            [
                'adresse'   => '12 rue du Marché',
                'ville'     => 'Paris',
                'telephone' => '+33612345678',
                'actif'     => true,
            ]
        );

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Admin Test', 'password' => 'password', 'boucherie_id' => $boucherie->id]
        );
        $admin->syncRoles(['admin']);

        // Boucher
        $boucher = User::firstOrCreate(
            ['email' => 'boucher@test.com'],
            ['name' => 'Boucher Test', 'password' => 'password', 'boucherie_id' => $boucherie->id]
        );
        $boucher->syncRoles(['boucher']);

        // Fournisseur — créer l'utilisateur puis l'entité Fournisseur liée
        $fournisseurUser = User::firstOrCreate(
            ['email' => 'fournisseur@test.com'],
            ['name' => 'Fournisseur Test', 'password' => 'password', 'boucherie_id' => null]
        );
        $fournisseurUser->syncRoles(['fournisseur']);

        // Entité Fournisseur liée au compte utilisateur
        $fournisseur = Fournisseur::firstOrCreate(
            ['user_id' => $fournisseurUser->id],
            [
                'boucherie_id' => $boucherie->id,
                'nom'          => 'Élevage Test',
                'contact'      => 'Fournisseur Test',
                'telephone'    => '+22600000001',
                'email'        => 'fournisseur@test.com',
                'adresse'      => 'Zone d\'élevage, Ouagadougou',
            ]
        );

        // Lien pivot requis pour créer des distributions vers cette boucherie
        $fournisseur->boucheries()->syncWithoutDetaching([$boucherie->id]);
    }
}
