<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Fournisseur;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(
        private readonly FournisseurBoucherieService $fournisseurBoucherieService,
    ) {}

    public function paginate(?string $boucherieId = null, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->when($boucherieId, function ($query) use ($boucherieId) {
                $query->where(function ($scoped) use ($boucherieId) {
                    $scoped->where('boucherie_id', $boucherieId)
                        ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'fournisseur'));
                });
            })
            ->with(['roles', 'boucherie.fournisseurAssigne.user', 'fournisseur.boucheries'])
            ->select(['id', 'name', 'email', 'boucherie_id', 'created_at'])
            ->latest()
            ->paginate($perPage);
    }

    public function findById(string $id): User
    {
        return User::with(['roles', 'boucherie.fournisseurAssigne.user', 'fournisseur.boucheries'])->findOrFail($id);
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $role            = $data['role'] ?? 'boucher';
            $fournisseurData = $data['fournisseur'] ?? null;
            unset($data['role'], $data['fournisseur']);

            $data['password']     = Hash::make($data['password']);
            $data['boucherie_id'] = $data['boucherie_id'] ?? null;

            $user = User::create($data);
            $user->assignRole($role);

            if ($role === 'fournisseur') {
                Fournisseur::create([
                    'user_id'      => $user->id,
                    'boucherie_id' => null,
                    'nom'          => $fournisseurData['nom']       ?? $user->name,
                    'contact'      => $fournisseurData['contact']   ?? null,
                    'telephone'    => $fournisseurData['telephone']  ?? null,
                    'email'        => $fournisseurData['email']      ?? $user->email,
                    'adresse'      => $fournisseurData['adresse']    ?? null,
                ]);
            }

            return $user->load(['roles', 'boucherie.fournisseurAssigne.user', 'fournisseur.boucheries']);
        });
    }

    public function update(string $id, array $data): User
    {
        return DB::transaction(function () use ($id, $data) {
            $user            = User::findOrFail($id);
            $fournisseurData = $data['fournisseur'] ?? null;
            $boucherieIds    = $data['boucherie_ids'] ?? null;
            unset($data['fournisseur'], $data['boucherie_ids']);

            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
                unset($data['role']);
            }

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            if ($fournisseurData) {
                Fournisseur::updateOrCreate(
                    ['user_id' => $user->id],
                    $fournisseurData,
                );
            }

            if ($boucherieIds !== null && $user->hasRole('fournisseur')) {
                $fournisseur = Fournisseur::query()
                    ->where('user_id', $user->id)
                    ->first();

                if ($fournisseur) {
                    $this->fournisseurBoucherieService->syncForFournisseur($fournisseur, $boucherieIds);
                }
            }

            return $user->load(['roles', 'boucherie.fournisseurAssigne.user', 'fournisseur.boucheries']);
        });
    }

    public function delete(string $id): void
    {
        User::findOrFail($id)->delete();
    }
}
