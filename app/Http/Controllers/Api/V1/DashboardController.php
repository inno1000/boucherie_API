<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Tableau de bord
 * @authenticated
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    /**
     * Hub « Aujourd'hui »
     *
     * Agrège résumé, tâches prioritaires et activité récente selon le rôle.
     *
     * @response {
     *   "data": {
     *     "role": "butcher",
     *     "date": "2026-05-26",
     *     "summary": { "ventes_jour": 5, "ca_jour": 150000, "alertes_stock": 1 },
     *     "tasks": [{ "id": "stock_alerts", "priority": "high", "count": 1, "href": "/stock/management", "action": "review_stock" }],
     *     "recent": [{ "kind": "sale", "id": "uuid", "label": "30 000 FCFA", "at": "2026-05-26T10:00:00+00:00" }]
     *   }
     * }
     */
    public function today(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->todayForUser($request->user()),
        ]);
    }
}
