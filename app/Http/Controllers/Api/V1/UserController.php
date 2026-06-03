<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Utilisateurs
 * @authenticated
 *
 * Gestion des comptes utilisateurs (admin uniquement).
 *
 * Les rôles disponibles sont : **admin**, **boucher**, **fournisseur**.
 *
 * Un boucher ou admin doit obligatoirement être rattaché à une boucherie (`boucherie_id`).
 * Un fournisseur n'a pas de `boucherie_id`. L'objet `fournisseur` est optionnel à la création ;
 * l'admin peut compléter ou modifier l'entité fournisseur ultérieurement via `PATCH /users/{id}`.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    /**
     * Liste des utilisateurs
     *
     * Retourne la liste paginée des utilisateurs. Si l'admin est rattaché à une boucherie,
     * les comptes de cette boucherie sont retournés ainsi que **tous les fournisseurs**
     * (leur `boucherie_id` utilisateur est null). Sinon tous les utilisateurs.
     *
     * @response {"data":[{"id":1,"name":"Alice Martin","email":"alice@boucherie.com","role":"admin","roles":["admin"],"boucherie_id":"uuid-boucherie","boucherie":null,"entite_fournisseur":null,"created_at":"2024-01-15T10:00:00.000Z"},{"id":3,"name":"Jean Éleveur","email":"jean@elevage.com","role":"fournisseur","roles":["fournisseur"],"boucherie_id":null,"boucherie":null,"entite_fournisseur":{"id":"uuid-fourn","nom":"Élevage du Sahel","contact":"Jean Éleveur","telephone":"+22600000002","email":"contact@elevage.com","adresse":"Zone d'élevage"},"created_at":"2024-01-15T10:00:00.000Z"}],"links":{"first":"http://localhost/api/v1/users?page=1","last":"http://localhost/api/v1/users?page=1","prev":null,"next":null},"meta":{"current_page":1,"from":1,"last_page":1,"per_page":15,"to":2,"total":2}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->service->paginate($request->user()->boucherie_id)
        );
    }

    /**
     * Créer un utilisateur
     *
     * ### Créer un boucher ou un admin
     * ```json
     * {
     *   "name": "Alice Martin",
     *   "email": "alice@boucherie.com",
     *   "password": "motdepasse8",
     *   "role": "boucher",
     *   "boucherie_id": "uuid-de-la-boucherie"
     * }
     * ```
     *
     * ### Créer un fournisseur
     * ```json
     * {
     *   "name": "Jean Éleveur",
     *   "email": "jean@elevage.com",
     *   "password": "motdepasse8",
     *   "role": "fournisseur",
     *   "fournisseur": {
     *     "nom": "Élevage du Sahel",
     *     "contact": "Jean Éleveur",
     *     "telephone": "+22600000002",
     *     "email": "contact@elevage.com",
     *     "adresse": "Zone d'élevage, Ouagadougou"
     *   }
     * }
     * ```
     *
     * @response 201 {"data":{"id":2,"name":"Bob Dupont","email":"bob@boucherie.com","role":"boucher","roles":["boucher"],"boucherie_id":"uuid-boucherie","boucherie":{"id":"uuid-boucherie","nom":"Boucherie Centrale","adresse":"12 rue du Marché","ville":"Paris","telephone":"+33600000001","actif":true},"entite_fournisseur":null,"created_at":"2024-01-15T10:00:00.000Z"},"message":"Utilisateur créé avec succès."}
     * @response 201 scenario="Fournisseur créé" {"data":{"id":3,"name":"Jean Éleveur","email":"jean@elevage.com","role":"fournisseur","roles":["fournisseur"],"boucherie_id":null,"boucherie":null,"entite_fournisseur":{"id":"uuid-fourn","nom":"Élevage du Sahel","contact":"Jean Éleveur","telephone":"+22600000002","email":"contact@elevage.com","adresse":"Zone d'élevage, Ouagadougou","created_at":"2024-01-15T10:00:00.000Z"},"created_at":"2024-01-15T10:00:00.000Z"},"message":"Utilisateur créé avec succès."}
     * @response 422 {"message":"La boucherie est obligatoire pour ce rôle.","errors":{"boucherie_id":["La boucherie est obligatoire pour ce rôle."]}}
     * @response 422 scenario="Email déjà utilisé" {"message":"The email has already been taken.","errors":{"email":["The email has already been taken."]}}
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated());

        return response()->json([
            'data'    => new UserResource($user),
            'message' => 'Utilisateur créé avec succès.',
        ], 201);
    }

    /**
     * Détail d'un utilisateur
     *
     * @response {"data":{"id":1,"name":"Alice Martin","email":"alice@boucherie.com","role":"admin","roles":["admin"],"boucherie_id":"uuid-boucherie","boucherie":{"id":"uuid-boucherie","nom":"Boucherie Centrale","adresse":"12 rue du Marché","ville":"Paris","telephone":"+33600000001","actif":true},"entite_fournisseur":null,"created_at":"2024-01-15T10:00:00.000Z"}}
     * @response 404 {"message":"Not found."}
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($this->service->findById($id)),
        ]);
    }

    /**
     * Mettre à jour un utilisateur
     *
     * Tous les champs sont optionnels. Pour modifier l'entité fournisseur, passer l'objet `fournisseur`.
     *
     * @response {"data":{"id":1,"name":"Alice Martin","email":"alice@boucherie.com","role":"boucher","roles":["boucher"],"boucherie_id":"uuid-boucherie","boucherie":null,"entite_fournisseur":null,"created_at":"2024-01-15T10:00:00.000Z"},"message":"Utilisateur mis à jour avec succès."}
     * @response 404 {"message":"Not found."}
     * @response 422 {"message":"The email has already been taken.","errors":{"email":["The email has already been taken."]}}
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = $this->service->update($id, $request->validated());

        return response()->json([
            'data'    => new UserResource($user),
            'message' => 'Utilisateur mis à jour avec succès.',
        ]);
    }

    /**
     * Supprimer un utilisateur
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
