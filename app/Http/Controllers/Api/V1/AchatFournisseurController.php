<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAchatFournisseurRequest;
use App\Http\Resources\AchatFournisseurResource;
use App\Services\AchatFournisseurService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Achats Fournisseurs
 * @authenticated
 * Enregistrement des achats d'animaux auprès des fournisseurs.
 * Chaque achat crée automatiquement les animaux associés.
 */
class AchatFournisseurController extends Controller
{
    public function __construct(private readonly AchatFournisseurService $service) {}

    /**
     * Liste des achats fournisseurs
     *
     * @response {"data":[{"id":1,"boucherie_id":1,"fournisseur_id":1,"fournisseur":null,"user_id":1,"date_achat":"2024-01-15","montant_total":540000,"notes":"3 bœufs adultes","animaux":[],"created_at":"2024-01-15T10:00:00.000Z","updated_at":"2024-01-15T10:00:00.000Z"}],"links":{"first":"http://localhost/api/v1/achats-fournisseurs?page=1","last":"http://localhost/api/v1/achats-fournisseurs?page=1","prev":null,"next":null},"meta":{"current_page":1,"from":1,"last_page":1,"path":"http://localhost/api/v1/achats-fournisseurs","per_page":15,"to":1,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user          = $request->user();
        $fournisseurId = $user->hasRole('fournisseur') ? $user->fournisseur?->id : null;

        return AchatFournisseurResource::collection(
            $this->service->paginate($user->boucherie_id, $fournisseurId)
        );
    }

    /**
     * Enregistrer un achat fournisseur
     *
     * Crée l'achat et génère automatiquement les animaux associés.
     * Pour un fournisseur authentifié, `fournisseur_id` n'est pas requis dans le corps —
     * il est résolu automatiquement depuis le profil de l'utilisateur connecté.
     * Pour un boucher ou admin, `fournisseur_id` est obligatoire.
     *
     * @response 201 {"data":{"id":1,"boucherie_id":null,"fournisseur_id":"uuid-fourn","user_id":"uuid-user","date_achat":"2024-01-15","montant_total":540000,"notes":"3 bœufs adultes","animaux":[{"id":"uuid","fournisseur_id":"uuid-fourn","espece":"bovin","poids_vif_kg":350.5,"prix_achat":180000,"numero_tag":"TAG-2024-001","statut":"en_attente"}],"created_at":"2024-01-15T10:00:00.000Z"},"message":"Achat enregistré avec succès."}
     * @response 422 {"message":"Le fournisseur_id est obligatoire pour ce rôle.","errors":{"fournisseur_id":["The fournisseur_id field is required."]}}
     * @response 422 scenario="Entité fournisseur manquante" {"message":"Votre profil fournisseur n'est pas encore configuré. Contactez l'administrateur."}
     */
    public function store(StoreAchatFournisseurRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('fournisseur')) {
            $fournisseurId = $user->fournisseur?->id;
            if (! $fournisseurId) {
                abort(422, "Votre profil fournisseur n'est pas encore configuré. Contactez l'administrateur.");
            }
        } else {
            $fournisseurId = $request->validated()['fournisseur_id'] ?? null;
        }

        $achat = $this->service->create(
            $request->validated(),
            $user->boucherie_id,
            $user->id,
            $fournisseurId,
            $user,
        );

        return response()->json([
            'data'    => new AchatFournisseurResource($achat),
            'message' => 'Achat enregistré avec succès.',
        ], 201);
    }

    /**
     * Détail d'un achat fournisseur
     *
     * @response {"data":{"id":1,"boucherie_id":1,"fournisseur_id":1,"fournisseur":{"id":1,"boucherie_id":1,"nom":"Élevage Dupont","contact":"Jean Dupont","email":"jean@elevage.com","telephone":"+33612345678","adresse":"Route de la Ferme, 75001 Paris","created_at":"2024-01-15T10:00:00.000Z","updated_at":"2024-01-15T10:00:00.000Z"},"user_id":1,"date_achat":"2024-01-15","montant_total":540000,"notes":"3 bœufs adultes","animaux":[{"id":1,"boucherie_id":1,"achat_fournisseur_id":1,"espece":"bovin","poids_vif_kg":350.5,"prix_achat":180000,"numero_tag":"TAG-2024-001","statut":"en_attente","created_at":"2024-01-15T10:00:00.000Z","updated_at":"2024-01-15T10:00:00.000Z"}],"created_at":"2024-01-15T10:00:00.000Z","updated_at":"2024-01-15T10:00:00.000Z"}}
     * @response 404 {"message":"Not found."}
     */
    public function show(string $id): JsonResponse
    {
        $achat = $this->service->findById($id);
        $this->authorize('view', $achat);

        return response()->json([
            'data' => new AchatFournisseurResource($achat),
        ]);
    }
}
