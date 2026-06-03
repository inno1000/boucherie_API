<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Statistiques
 * @authenticated
 *
 * Indicateurs clés par rôle. Le paramètre `periode` filtre les données :
 * - `semaine` — 7 derniers jours
 * - `mois` — 30 derniers jours (défaut)
 * - `annee` — 12 derniers mois
 */
class StatsController extends Controller
{
    public function __construct(private readonly StatsService $service) {}

    /**
     * Stats Administrateur
     *
     * Vue globale : ventes toutes boucheries, état des stocks, versements par statut,
     * top 5 produits, top 5 boucheries et top 5 fournisseurs.
     *
     * @queryParam periode string Fenêtre temporelle : `semaine`, `mois` (défaut), `annee`. Example: mois
     *
     * @response {
     *   "data": {
     *     "periode": "mois",
     *     "ventes": { "total": 120, "montant_total": 3600000, "montant_moyen": 30000 },
     *     "stocks": { "total_references": 25, "alertes_rupture": 3 },
     *     "versements": {
     *       "en_attente": { "count": 4, "montant": 400000 },
     *       "valides":    { "count": 18, "montant": 1800000 },
     *       "rejetes":    { "count": 2,  "montant": 100000 }
     *     },
     *     "top_produits":    [{ "produit": "Côtelettes", "quantite_vendue": 250, "chiffre_affaires": 750000 }],
     *     "top_boucheries":  [{ "boucherie": "Boucherie Centrale", "nb_ventes": 80, "chiffre_affaires": 2400000 }],
     *     "top_fournisseurs":[{ "fournisseur": "Jean Éleveur", "montant_recu": 900000 }]
     *   }
     * }
     */
    public function admin(Request $request): JsonResponse
    {
        $periode = $this->resolvePeriode($request);

        return response()->json(['data' => $this->service->forAdmin($periode)]);
    }

    /**
     * Stats Boucher
     *
     * Pilotage de la boucherie : ventes, alertes stock, distributions reçues,
     * versements au fournisseur, top 5 produits et top 5 clients.
     *
     * @queryParam periode string Fenêtre temporelle : `semaine`, `mois` (défaut), `annee`. Example: mois
     *
     * @response {
     *   "data": {
     *     "periode": "mois",
     *     "ventes": { "total": 45, "montant_total": 1350000, "montant_moyen": 30000 },
     *     "stocks": {
     *       "total_references": 10,
     *       "alertes_rupture": 2,
     *       "produits_en_alerte": [{ "produit": "Gigot", "quantite": 1.5, "seuil_alerte": 5 }]
     *     },
     *     "distributions": { "en_attente": 3, "acceptees": 12, "rejetees": 0 },
     *     "versements": {
     *       "en_attente": { "count": 2, "montant": 200000 },
     *       "valides":    { "count": 7, "montant": 700000 },
     *       "rejetes":    { "count": 1, "montant": 50000 }
     *     },
     *     "top_produits": [{ "produit": "Côtelettes", "quantite_vendue": 90, "chiffre_affaires": 270000 }],
     *     "top_clients":  [{ "client": "Ali Traoré", "nb_ventes": 8, "total_achats": 240000 }]
     *   }
     * }
     */
    public function boucher(Request $request): JsonResponse
    {
        $periode = $this->resolvePeriode($request);
        $user    = $request->user();

        return response()->json(['data' => $this->service->forBoucher((string) $user->boucherie_id, $periode)]);
    }

    /**
     * Stats Fournisseur
     *
     * Suivi des créances : abattages et rendement, distributions par statut,
     * versements reçus (total dû vs perçu), top 5 boucheries clientes.
     *
     * @queryParam periode string Fenêtre temporelle : `semaine`, `mois` (défaut), `annee`. Example: mois
     *
     * @response {
     *   "data": {
     *     "periode": "mois",
     *     "abattages": { "total": 12, "poids_total_kg": 2400, "rendement_moyen": 58.5 },
     *     "distributions": { "en_attente": 4, "acceptees": 7, "rejetees": 1 },
     *     "versements": {
     *       "en_attente": { "count": 3, "montant": 300000 },
     *       "valides":    { "count": 9, "montant": 900000 },
     *       "rejetes":    { "count": 1, "montant": 50000 },
     *       "total_du": 1200000,
     *       "total_percu": 900000
     *     },
     *     "top_boucheries_clientes": [{ "boucherie": "Boucherie Centrale", "nb_distributions": 5, "quantite_totale": 800 }]
     *   }
     * }
     */
    public function fournisseur(Request $request): JsonResponse
    {
        $periode = $this->resolvePeriode($request);
        $user    = $request->user();

        return response()->json(['data' => $this->service->forFournisseur((string) $user->id, $periode)]);
    }

    private function resolvePeriode(Request $request): string
    {
        return in_array($request->query('periode'), ['jour', 'semaine', 'mois', 'annee'], true)
            ? $request->query('periode')
            : 'mois';
    }
}
