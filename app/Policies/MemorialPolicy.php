<?php

namespace App\Policies;

use App\Models\Memorial;
use App\Models\User;

class MemorialPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Memorial $memorial): bool
    {
        return $memorial->user_id === $user->id
            || $user->hasRole(['admin', 'super-admin'])
            || ($memorial->is_public && !$memorial->expires_at?->isPast());
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Memorial $memorial): bool
    {
        return $memorial->canBeEditedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Memorial $memorial): bool
    {
        return $memorial->user_id === $user->id
            || $user->hasRole(['admin', 'super-admin']);
    }
}
