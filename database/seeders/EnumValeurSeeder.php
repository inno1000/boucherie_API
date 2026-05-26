<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EnumValeur;
use Illuminate\Database\Seeder;

class EnumValeurSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            // ─── Config (systeme=false, frontend peut supprimer) ────────────
            'espece_animal' => [
                ['valeur' => 'bovin',    'libelle' => 'Bovin',    'systeme' => true, 'ordre' => 1],
                ['valeur' => 'ovin',     'libelle' => 'Ovin',     'systeme' => true, 'ordre' => 2],
                ['valeur' => 'caprin',   'libelle' => 'Caprin',   'systeme' => true, 'ordre' => 3],
                ['valeur' => 'porcin',   'libelle' => 'Porcin',   'systeme' => true, 'ordre' => 4],
                ['valeur' => 'volaille', 'libelle' => 'Volaille', 'systeme' => true, 'ordre' => 5],
            ],
            'categorie_produit' => [
                ['valeur' => 'viande_rouge', 'libelle' => 'Viande rouge',  'systeme' => false, 'ordre' => 1],
                ['valeur' => 'abats',        'libelle' => 'Abats',         'systeme' => false, 'ordre' => 2],
                ['valeur' => 'charcuterie',  'libelle' => 'Charcuterie',   'systeme' => false, 'ordre' => 3],
                ['valeur' => 'autre',        'libelle' => 'Autre',         'systeme' => false, 'ordre' => 4],
            ],
            'unite_produit' => [
                ['valeur' => 'kg',    'libelle' => 'Kilogramme', 'systeme' => false, 'ordre' => 1],
                ['valeur' => 'g',     'libelle' => 'Gramme',     'systeme' => false, 'ordre' => 2],
                ['valeur' => 'piece', 'libelle' => 'Pièce',      'systeme' => false, 'ordre' => 3],
            ],
            'mode_paiement' => [
                ['valeur' => 'especes',      'libelle' => 'Espèces',      'systeme' => false, 'ordre' => 1],
                ['valeur' => 'mobile_money', 'libelle' => 'Mobile Money', 'systeme' => false, 'ordre' => 2],
                ['valeur' => 'cheque',       'libelle' => 'Chèque',       'systeme' => false, 'ordre' => 3],
                ['valeur' => 'virement',     'libelle' => 'Virement',     'systeme' => false, 'ordre' => 4],
                ['valeur' => 'credit',       'libelle' => 'Crédit',       'systeme' => false, 'ordre' => 5],
            ],
            // ─── Logique métier (systeme=true, protégés) ────────────────────
            'statut_animal' => [
                ['valeur' => 'en_attente', 'libelle' => 'En attente', 'systeme' => true, 'ordre' => 1],
                ['valeur' => 'abattu',     'libelle' => 'Abattu',     'systeme' => true, 'ordre' => 2],
                ['valeur' => 'vendu',      'libelle' => 'Vendu',      'systeme' => true, 'ordre' => 3],
            ],
            'type_vente' => [
                ['valeur' => 'comptoir',  'libelle' => 'Comptoir',  'systeme' => true, 'ordre' => 1],
                ['valeur' => 'livraison', 'libelle' => 'Livraison', 'systeme' => true, 'ordre' => 2],
            ],
            'statut_vente' => [
                ['valeur' => 'en_cours', 'libelle' => 'En cours', 'systeme' => true, 'ordre' => 1],
                ['valeur' => 'validee',  'libelle' => 'Validée',  'systeme' => true, 'ordre' => 2],
                ['valeur' => 'annulee',  'libelle' => 'Annulée',  'systeme' => true, 'ordre' => 3],
                ['valeur' => 'livree',   'libelle' => 'Livrée',   'systeme' => true, 'ordre' => 4],
            ],
            'statut_livraison' => [
                ['valeur' => 'en_attente', 'libelle' => 'En attente', 'systeme' => true, 'ordre' => 1],
                ['valeur' => 'en_cours',   'libelle' => 'En cours',   'systeme' => true, 'ordre' => 2],
                ['valeur' => 'livree',     'libelle' => 'Livrée',     'systeme' => true, 'ordre' => 3],
                ['valeur' => 'echec',      'libelle' => 'Échec',      'systeme' => true, 'ordre' => 4],
            ],
            'type_mouvement' => [
                ['valeur' => 'entree',     'libelle' => 'Entrée',      'systeme' => true, 'ordre' => 1],
                ['valeur' => 'sortie',     'libelle' => 'Sortie',      'systeme' => true, 'ordre' => 2],
                ['valeur' => 'ajustement', 'libelle' => 'Ajustement',  'systeme' => true, 'ordre' => 3],
                ['valeur' => 'perte',      'libelle' => 'Perte',       'systeme' => true, 'ordre' => 4],
                ['valeur' => 'inventaire', 'libelle' => 'Inventaire',  'systeme' => true, 'ordre' => 5],
            ],
        ];

        foreach ($definitions as $type => $valeurs) {
            foreach ($valeurs as $item) {
                EnumValeur::firstOrCreate(
                    ['type' => $type, 'valeur' => $item['valeur'], 'boucherie_id' => null],
                    ['libelle' => $item['libelle'], 'systeme' => $item['systeme'], 'ordre' => $item['ordre']]
                );
            }
        }
    }
}
