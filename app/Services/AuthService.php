<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $this->loadUserRelations($user), 'token' => $token];
    }

    public function login(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            abort(401, 'Adresse email ou mot de passe incorrect.');
        }

        $user->tokens()->delete();
        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $this->loadUserRelations($user), 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }

    public function loadUserRelations(User $user): User
    {
        return $user->load([
            'roles',
            'boucherie.fournisseurAssigne.user',
            'fournisseur.boucheries',
        ]);
    }

    public function updatePassword(User $user, string $password): User
    {
        $user->update(['password' => Hash::make($password)]);

        return $this->loadUserRelations($user->fresh());
    }

    /**
     * @param  array{name?: string, email?: string}  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $this->loadUserRelations($user->fresh());
    }
}
