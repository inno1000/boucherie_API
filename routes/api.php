<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AbattageController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AchatFournisseurController;
use App\Http\Controllers\Api\V1\AnimalController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BoucherieController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\DistributionController;
use App\Http\Controllers\Api\V1\EnumValeurController;
use App\Http\Controllers\Api\V1\FournisseurController;
use App\Http\Controllers\Api\V1\LivraisonController;
use App\Http\Controllers\Api\V1\PaiementController;
use App\Http\Controllers\Api\V1\ProduitController;
use App\Http\Controllers\Api\V1\ReceptionController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\StatsController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RecetteJournaliereController;
use App\Http\Controllers\Api\V1\VenteController;
use App\Http\Controllers\Api\V1\VersementController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Auth ──────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login',    [AuthController::class, 'login'])->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::patch('me', [AuthController::class, 'updateMe']);
            Route::patch('password', [AuthController::class, 'updatePassword']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {

        // ── Pièces jointes audio ─────────────────────────────────────────────
        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::get('attachments/{attachment}/stream', [AttachmentController::class, 'stream']);

        // ── Tableau de bord ──────────────────────────────────────────────────
        Route::get('dashboard/today', [DashboardController::class, 'today']);

        // ── Statistiques ─────────────────────────────────────────────────────
        Route::middleware('role:admin')->get('stats/admin',       [StatsController::class, 'admin']);
        Route::middleware('role:admin|boucher')->get('stats/boucher',     [StatsController::class, 'boucher']);
        Route::middleware('role:fournisseur|admin')->get('stats/fournisseur', [StatsController::class, 'fournisseur']);

        // ── Référentiels (Enums) — tous les rôles authentifiés ──────────────
        Route::prefix('referentiels/{type}')->group(function () {
            Route::get('/',        [EnumValeurController::class, 'index']);
            Route::post('/',       [EnumValeurController::class, 'store']);
            Route::patch('/{id}',  [EnumValeurController::class, 'update']);
            Route::delete('/{id}', [EnumValeurController::class, 'destroy']);
        });

        // ── Admin uniquement — gestion des utilisateurs ──────────────────────
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('users', UserController::class);
        });

        // ── Admin + Boucher ──────────────────────────────────────────────────
        Route::middleware('role:admin|boucher')->group(function () {
            // Écriture boucheries et produits (lecture ouverte au fournisseur plus bas)
            Route::apiResource('boucheries', BoucherieController::class)->except(['index', 'show']);
            Route::apiResource('produits',   ProduitController::class)->except(['index', 'show']);

            Route::apiResource('fournisseurs', FournisseurController::class);
            Route::apiResource('clients', ClientController::class);


            Route::get('stocks',                    [StockController::class, 'index']);
            Route::get('stocks/{stock}',            [StockController::class, 'show']);
            Route::get('stocks/{stock}/mouvements', [StockController::class, 'mouvements']);
            Route::post('stocks/{stock}/ajuster',   [StockController::class, 'ajuster']);

            Route::get('ventes',                  [VenteController::class, 'index']);
            Route::post('ventes',                 [VenteController::class, 'store']);
            Route::get('ventes/{vente}',          [VenteController::class, 'show']);
            Route::patch('ventes/{vente}/statut', [VenteController::class, 'updateStatut']);
            Route::delete('ventes/{vente}',       [VenteController::class, 'destroy']);

            Route::post('ventes/{vente}/paiements', [PaiementController::class, 'store']);

            Route::post('ventes/{vente}/livraison',  [LivraisonController::class, 'store']);
            Route::patch('ventes/{vente}/livraison', [LivraisonController::class, 'update']);
        });

        // ── Admin + Boucher + Fournisseur ────────────────────────────────────
        Route::middleware('role:admin|boucher|fournisseur')->group(function () {
            // Lecture seule — nécessaire au fournisseur pour distribuer et gérer ses animaux
            Route::get('boucheries',             [BoucherieController::class, 'index']);
            Route::get('boucheries/{boucherie}', [BoucherieController::class, 'show']);
            Route::get('produits',               [ProduitController::class, 'index']);
            Route::get('produits/{produit}',     [ProduitController::class, 'show']);
            Route::get('animaux',                [AnimalController::class, 'index']);
            Route::get('animaux/{animal}',       [AnimalController::class, 'show']);
            Route::patch('animaux/{animal}',     [AnimalController::class, 'update']);
            Route::delete('animaux/{animal}',    [AnimalController::class, 'destroy']);

            // Achats fournisseurs — accessibles aux 3 rôles (logique de filtrage dans le controller)
            Route::get('achats-fournisseurs',         [AchatFournisseurController::class, 'index']);
            Route::post('achats-fournisseurs',        [AchatFournisseurController::class, 'store']);
            Route::get('achats-fournisseurs/{achat}', [AchatFournisseurController::class, 'show']);

            Route::get('abattages',            [AbattageController::class, 'index']);
            Route::post('abattages',           [AbattageController::class, 'store']);
            Route::get('abattages/{abattage}', [AbattageController::class, 'show']);

            Route::get('ventes/{vente}/paiements', [PaiementController::class, 'index']);

            // Distributions
            Route::get('distributions',                      [DistributionController::class, 'index']);
            Route::get('distributions/{distribution}',       [DistributionController::class, 'show']);
            Route::patch('distributions/{distribution}/annuler', [DistributionController::class, 'annuler']);

            // Réceptions
            Route::get('receptions',             [ReceptionController::class, 'index']);
            Route::get('receptions/{reception}', [ReceptionController::class, 'show']);

            // Versements
            Route::get('versements',             [VersementController::class, 'index']);
            Route::get('versements/{versement}', [VersementController::class, 'show']);
        });

        // ── Fournisseur uniquement ────────────────────────────────────────────
        Route::middleware('role:fournisseur|admin')->group(function () {
            Route::post('distributions',                   [DistributionController::class, 'store']);
            Route::patch('versements/{versement}/valider', [VersementController::class, 'valider']);
            Route::patch('versements/{versement}/rejeter', [VersementController::class, 'rejeter']);
        });

        // ── Boucher uniquement (création réception et versement) ─────────────
        Route::middleware('role:boucher|admin')->group(function () {
            Route::post('receptions',  [ReceptionController::class, 'store']);
            Route::post('versements',  [VersementController::class, 'store']);
        });

        // ── Recettes journalières v2 ──────────────────────────────────────────
        Route::middleware('role:admin|boucher|fournisseur')->group(function () {
            Route::get('recettes',            [RecetteJournaliereController::class, 'index']);
            Route::get('recettes/{recette}',  [RecetteJournaliereController::class, 'show']);
        });

        Route::middleware('role:boucher|admin')->group(function () {
            Route::post('recettes', [RecetteJournaliereController::class, 'store']);
        });

        Route::middleware('role:fournisseur|admin')->group(function () {
            Route::patch('recettes/{recette}/valider', [RecetteJournaliereController::class, 'valider']);
            Route::patch('recettes/{recette}/rejeter', [RecetteJournaliereController::class, 'rejeter']);
        });
    });
});
