<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesListQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAnimalRequest;
use App\Http\Resources\AnimalResource;
use App\Services\AnimalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Animaux
 * @authenticated
 * Consultation des animaux (créés via les achats fournisseurs).
 * Filtrable par statut : `en_attente`, `abattu`, `vendu`.
 */
class AnimalController extends Controller
{
    use ResolvesListQuery;

    public function __construct(private readonly AnimalService $service) {}

    /**
     * Liste des animaux
     *
     * Filtrable via le paramètre `?statut=en_attente|abattu|vendu`.
     *
     * @queryParam statut string Filtre par statut. Exemple: en_attente
     * @response {"data":[{"id":1,"boucherie_id":1,"achat_fournisseur_id":1,"espece":"bovin","poids_vif_kg":350.5,"prix_achat":180000,"numero_tag":"TAG-2024-001","statut":"en_attente","created_at":"2024-01-15T10:00:00.000Z","updated_at":"2024-01-15T10:00:00.000Z"}],"links":{"first":"http://localhost/api/v1/animaux?page=1","last":"http://localhost/api/v1/animaux?page=1","prev":null,"next":null},"meta":{"current_page":1,"from":1,"last_page":1,"path":"http://localhost/api/v1/animaux","per_page":15,"to":1,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user    = $request->user();
        $filters = ['statut' => $this->statutFromRequest($request)];
        $perPage = $this->perPageFromRequest($request);

        $paginator = $user->hasRole('fournisseur')
            ? $this->service->paginateByFournisseurUser($user->id, $filters, $perPage)
            : $this->service->paginate($user->boucherie_id, $filters, $perPage);

        return AnimalResource::collection($paginator);
    }

    /**
     * Détail d'un animal
     *
     * @response {"data":{"id":1,"boucherie_id":1,"achat_fournisseur_id":1,"espece":"bovin","poids_vif_kg":350.5,"prix_achat":180000,"numero_tag":"TAG-2024-001","statut":"en_attente","created_at":"2024-01-15T10:00:00.000Z","updated_at":"2024-01-15T10:00:00.000Z"}}
     * @response 404 {"message":"Not found."}
     */
    public function show(string $id): JsonResponse
    {
        $animal = $this->service->findById($id);
        $this->authorize('view', $animal);

        return response()->json([
            'data' => new AnimalResource($animal),
        ]);
    }

    /**
     * Modifier un animal non abattu
     *
     * @response {"data":{"id":"uuid","espece":"bovin","poids_vif_kg":350,"prix_achat":200000,"numero_tag":"TAG-001","statut":"en_attente"},"message":"Animal mis à jour."}
     * @response 403 {"message":"Action non autorisée."}
     * @response 422 {"message":"Seuls les animaux non abattus peuvent être modifiés."}
     */
    public function update(UpdateAnimalRequest $request, string $id): JsonResponse
    {
        $animal = $this->service->findById($id);
        $this->authorize('update', $animal);

        $updated = $this->service->update($id, $request->validated());

        return response()->json([
            'data'    => new AnimalResource($updated),
            'message' => 'Animal mis à jour.',
        ]);
    }

    /**
     * Supprimer un animal non abattu
     *
     * @response 200 {"message":"Animal supprimé."}
     * @response 403 {"message":"Action non autorisée."}
     * @response 422 {"message":"Seuls les animaux non abattus peuvent être supprimés."}
     */
    public function destroy(string $id): JsonResponse
    {
        $animal = $this->service->findById($id);
        $this->authorize('delete', $animal);

        $this->service->delete($id);

        return response()->json([
            'message' => 'Animal supprimé.',
        ]);
    }
}
