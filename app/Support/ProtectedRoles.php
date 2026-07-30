<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Who may hand out which role, and who may edit whom.
 *
 * The user-management screens sit behind `role:admin|super-admin`, but the role they wrote
 * was only validated as `exists:roles,name` — so a plain admin could create an account with
 * the `super-admin` role, or promote themselves into it, and walk straight into the reseller
 * program: suspending live businesses, "Login as" on any reseller owner, recording payments.
 * The routes draw that line deliberately (routes/web.php gates the whole reseller block on
 * `role:super-admin`); nothing was holding it at the point roles are actually assigned.
 *
 * Two rules, both enforced server-side and reflected in the pickers so the UI does not offer
 * what the request would refuse:
 *
 *   1. Only a super-admin may grant or revoke `super-admin`.
 *   2. Only a super-admin may edit or delete an account that already holds it — otherwise an
 *      admin could simply reset a super-admin's password and sign in as them instead.
 */
class ProtectedRoles
{
    /** Roles that only a super-admin may assign, revoke, or act upon. */
    public const ELEVATED = ['super-admin'];

    public static function isElevated(?string $role): bool
    {
        return in_array((string) $role, self::ELEVATED, true);
    }

    public static function actorMayAssign(?User $actor, ?string $role): bool
    {
        if (! self::isElevated($role)) {
            return true;
        }

        return (bool) $actor?->hasRole('super-admin');
    }

    /**
     * Whether $actor may modify $target at all. Anyone elevated is off-limits to a
     * non-super-admin, whatever the request is trying to change about them.
     */
    public static function actorMayManage(?User $actor, User $target): bool
    {
        foreach (self::ELEVATED as $role) {
            if ($target->hasRole($role) && ! $actor?->hasRole('super-admin')) {
                return false;
            }
        }

        return true;
    }

    /** 403 unless $actor may grant $role. */
    public static function guardAssignment(?User $actor, ?string $role): void
    {
        abort_unless(
            self::actorMayAssign($actor, $role),
            403,
            'Only a super-admin can assign the super-admin role.'
        );
    }

    /** 403 unless $actor may modify $target. */
    public static function guardTarget(?User $actor, User $target): void
    {
        abort_unless(
            self::actorMayManage($actor, $target),
            403,
            'Only a super-admin can manage a super-admin account.'
        );
    }

    /**
     * The roles $actor may actually put on someone — what the pickers should list. Returning
     * a query rather than a collection so callers keep their existing ordering/pagination.
     */
    public static function assignableQuery(?User $actor): Builder
    {
        $query = Role::query();

        if (! $actor?->hasRole('super-admin')) {
            $query->whereNotIn('name', self::ELEVATED);
        }

        return $query;
    }
}
