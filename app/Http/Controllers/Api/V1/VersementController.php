<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\LinksAttachments;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVersementRequest;
use App\Http\Requests\UpdateVersementStatutRequest;
use App\Http\Resources\VersementResource;
use App\Services\FournisseurBoucherieService;
use App\Services\VersementService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Versements
 * @authenticated
 * La boucherie effectue des versements au fournisseur.
 * Le fournisseur valide ou rejette chaque versement.
 */
class VersementController extends Controller
{
    use LinksAttachments;

    public function __construct(private readonly VersementService $service) {}

    /**
     * Liste des versements
     *
     * Fournisseur : versements qui lui sont destinés. Boucher/Admin : versements de sa boucherie.
     *
     * @response {"data":[{"id":"uuid","boucherie_id":"uuid","fournisseur_user_id":3,"montant":"150000.00","mode_paiement":"mobile_money","date_versement":"2024-01-20","reference":"TXN-2024-001","statut":"en_attente","motif_rejet":null,"notes":null,"valide_par":null,"valide_le":null,"created_at":"2024-01-20T10:00:00.000Z","updated_at":"2024-01-20T10:00:00.000Z"}],"links":{"first":"http://localhost/api/v1/versements?page=1","last":"http://localhost/api/v1/versements?page=1","prev":null,"next":null},"meta":{"current_page":1,"from":1,"last_page":1,"path":"http://localhost/api/v1/versements","per_page":15,"to":1,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $paginator = match (true) {
            $user->hasRole('fournisseur') => $this->service->paginateByFournisseur($user->id),
            $user->hasRole('boucher')     => $this->service->paginateByBoucherie((string) $user->boucherie_id),
            default                       => $this->service->paginateAll(),
        };

        return VersementResource::collection($paginator);
    }

    /**
     * Créer un versement
     *
     * La boucherie déclare un versement effectué au fournisseur. Statut initial : `en_attente`.
     *
     * @response 201 {"data":{"id":"uuid","boucherie_id":"uuid","fournisseur_user_id":3,"montant":"150000.00","mode_paiement":"mobile_money","date_versement":"2024-01-20","reference":"TXN-2024-001","statut":"en_attente","motif_rejet":null,"notes":null,"valide_par":null,"valide_le":null,"created_at":"2024-01-20T10:00:00.000Z","updated_at":"2024-01-20T10:00:00.000Z"},"message":"Versement enregistré avec succès."}
     * @response 422 {"message":"The montant field is required.","errors":{"montant":["The montant field is required."]}}
     */
    public function store(StoreVersementRequest $request): JsonResponse
    {
        $user          = $request->user();
        $data          = $request->validated();
        $attachmentIds = $this->stripAttachmentIds($data);

        if ($user->hasRole('boucher')) {
            $expectedFournisseurUserId = app(FournisseurBoucherieService::class)
                ->fournisseurUserIdForBoucherie($user->boucherie_id);

            if ($expectedFournisseurUserId === null) {
                throw ValidationException::withMessages([
                    'fournisseur_user_id' => [
                        'Aucun fournisseur n\'est rattaché à votre boucherie. Contactez l\'administrateur.',
                    ],
                ]);
            }

            if ((int) ($data['fournisseur_user_id'] ?? 0) !== $expectedFournisseurUserId) {
                abort(403, 'Ce versement doit être adressé à votre fournisseur assigné.');
            }

            $data['fournisseur_user_id'] = $expectedFournisseurUserId;
        }

        $versement = $this->linkAttachments(
            $this->service->create($data, $user->boucherie_id),
            $attachmentIds,
            $request->user(),
        );

        return response()->json([
            'data'    => new VersementResource($versement),
            'message' => 'Versement enregistré avec succès.',
        ], 201);
    }

    /**
     * Détail d'un versement
     *
     * @response {"data":{"id":"uuid","boucherie_id":"uuid","fournisseur_user_id":3,"montant":"150000.00","mode_paiement":"mobile_money","date_versement":"2024-01-20","reference":"TXN-2024-001","statut":"en_attente","motif_rejet":null,"notes":null,"valide_par":null,"valide_le":null,"created_at":"2024-01-20T10:00:00.000Z","updated_at":"2024-01-20T10:00:00.000Z"}}
     * @response 404 {"message":"Not found."}
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'data' => new VersementResource($this->service->findById($id)),
        ]);
    }

    /**
     * Valider un versement (fournisseur)
     *
     * Le fournisseur confirme avoir reçu le paiement.
     *
     * @response {"data":{"id":"uuid","statut":"valide","valide_par":3,"valide_le":"2024-01-21T08:00:00.000Z"},"message":"Versement validé avec succès."}
     * @response 422 {"message":"Impossible de valider un versement au statut « valide »."}
     */
    public function valider(UpdateVersementStatutRequest $request, string $id): JsonResponse
    {
        $versement = $this->service->valider($id, $request->user()->id);

        return response()->json([
            'data'    => new VersementResource($versement),
            'message' => 'Versement validé avec succès.',
        ]);
    }

    /**
     * Rejeter un versement (fournisseur)
     *
     * Le fournisseur conteste le versement et indique un motif.
     *
     * @response {"data":{"id":"uuid","statut":"rejete","motif_rejet":"Montant incorrect, attendu 200 000 FCFA","valide_par":3,"valide_le":"2024-01-21T08:00:00.000Z"},"message":"Versement rejeté."}
     * @response 422 {"message":"Impossible de rejeter un versement au statut « rejete »."}
     */
    public function rejeter(UpdateVersementStatutRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();
        $attachmentIds = is_array($validated['attachment_ids'] ?? null)
            ? $validated['attachment_ids']
            : [];

        $versement = $this->service->rejeter(
            $id,
            $request->user()->id,
            $validated['motif_rejet'] ?? null,
        );

        $versement = $this->linkAttachments($versement, $attachmentIds, $request->user());

        return response()->json([
            'data'    => new VersementResource($versement),
            'message' => 'Versement rejeté.',
        ]);
    }
}
