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
            || $memorial->isManagedByResellerStaff($user)
            || ($memorial->is_public && !$memorial->expires_at?->isPast());
    }

    /**
     * Determine whether the user can pull the memorial's report.
     *
     * Deliberately much narrower than view(): that one lets any visitor read a public
     * memorial, whereas this report carries visitor counts and reprints tribute messages
     * in one document. It is for the people responsible for the memorial — the owner,
     * their editors, the reseller hosting it, and platform admins — not for its audience.
     */
    public function report(User $user, Memorial $memorial): bool
    {
        return $memorial->canBeEditedBy($user);
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
            || $user->hasRole(['admin', 'super-admin'])
            || $memorial->isManagedByResellerStaff($user);
    }
}
