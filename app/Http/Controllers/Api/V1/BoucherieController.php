<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\LinksAttachments;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBoucherieRequest;
use App\Http\Requests\UpdateBoucherieRequest;
use App\Http\Resources\BoucherieResource;
use App\Services\BoucherieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Boucheries
 * @authenticated
 * Gestion des établissements (admin uniquement).
 */
class BoucherieController extends Controller
{
    use LinksAttachments;

    public function __construct(private readonly BoucherieService $service) {}

    /**
     * Liste des boucheries
     *
     * Retourne la liste paginée de toutes les boucheries.
     *
     * @response {"data":[{"id":1,"nom":"Boucherie Centrale","adresse":"12 rue du Marché","ville":"Paris","telephone":"+33612345678","actif":true,"created_at":"2024-01-15T10:00:00.000Z","updated_at":"2024-01-15T10:00:00.000Z"}],"links":{"first":"http://localhost/api/v1/boucheries?page=1","last":"http://localhost/api/v1/boucheries?page=1","prev":null,"next":null},"meta":{"current_page":1,"from":1,"last_page":1,"path":"http://localhost/api/v1/boucheries","per_page":15,"to":1,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        if ($user->hasRole('fournisseur')) {
            return BoucherieResource::collection(
                $this->service->paginateForFournisseurUser($user->id),
            );
        }

        return BoucherieResource::collection($this->service->paginate());
    }

    /**
     * Créer une boucherie
     *
     * @response 201 {"data":{"id":1,"nom":"Boucherie Centrale","adresse":"12 rue du Marché","ville":"Paris","telephone":"+33612345678","actif":true,"created_at":"2024-01-15T10:00:00.000Z","updated_at":"2024-01-15T10:00:00.000Z"},"message":"Boucherie créée avec succès."}
     * @response 422 {"message":"The nom field is required.","errors":{"nom":["The nom field is required."]}}
     */
    public function store(StoreBoucherieRequest $request): JsonResponse
    {
        $data          = $request->validated();
        $attachmentIds = $this->stripAttachmentIds($data);

        $boucherie = $this->linkAttachments(
            $this->service->create($data),
            $attachmentIds,
            $request->user(),
        );

        return response()->json([
            'data'    => new BoucherieResource($boucherie),
            'message' => 'Boucherie créée avec succès.',
        ], 201);
    }

    /**
     * Détail d'une boucherie
     *
     * @response {"data":{"id":1,"nom":"Boucherie Centrale","adresse":"12 rue du Marché","ville":"Paris","telephone":"+33612345678","actif":true,"created_at":"2024-01-15T10:00:00.000Z","updated_at":"2024-01-15T10:00:00.000Z"}}
     * @response 404 {"message":"Not found."}
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'data' => new BoucherieResource($this->service->findById($id)),
        ]);
    }

    /**
     * Mettre à jour une boucherie
     *
     * @response {"data":{"id":1,"nom":"Boucherie Centrale","adresse":"12 rue du Marché","ville":"Paris","telephone":"+33612345678","actif":true,"created_at":"2024-01-15T10:00:00.000Z","updated_at":"2024-01-15T10:00:00.000Z"},"message":"Boucherie mise à jour avec succès."}
     * @response 404 {"message":"Not found."}
     * @response 422 {"message":"The nom field is required.","errors":{"nom":["The nom field is required."]}}
     */
    public function update(UpdateBoucherieRequest $request, string $id): JsonResponse
    {
        $boucherie = $this->service->update($id, $request->validated());

        return response()->json([
            'data'    => new BoucherieResource($boucherie),
            'message' => 'Boucherie mise à jour avec succès.',
        ]);
    }

    /**
     * Supprimer une boucherie
     *
     * @response 204 {}
     * @response 404 {"message":"Not found."}
     */
    public function destroy(string $id): JsonResponse
    {
        $this->service->delete($id);

        return response()->json(null, 204);
    }
}
