<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Animal;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AnimalPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function view(User $user, Animal $animal): bool
    {
        return $this->owns($user, $animal);
    }

    public function update(User $user, Animal $animal): bool
    {
        return $this->owns($user, $animal) && $animal->statut === 'en_attente';
    }

    public function delete(User $user, Animal $animal): bool
    {
        return $this->owns($user, $animal) && $animal->statut === 'en_attente';
    }

    private function owns(User $user, Animal $animal): bool
    {
        if ($user->hasRole('fournisseur')) {
            return $animal->fournisseur?->user_id === $user->id;
        }

        return $user->boucherie_id !== null
            && $animal->boucherie_id !== null
            && $user->boucherie_id === $animal->boucherie_id;
    }
}
