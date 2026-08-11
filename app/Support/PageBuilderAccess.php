<?php

namespace App\Support;

use App\Models\Reseller;
use App\Models\User;

/**
 * Who may use a reseller's website / page builder.
 *
 * Normally it's a paid capability: the reseller's tier must include feature_page_builder.
 * The one exception is a super-admin who has "Login as"-ed into the reseller (tracked by
 * the impersonator_id session key, set only by the super-admin-gated loginAs action):
 * support and onboarding often mean building or fixing a reseller's pages — including their
 * homepage — for them, and that must not depend on whether their own tier has bought the
 * feature yet.
 *
 * Single source of truth so the controller gate and the sidebar nav can never disagree
 * about whether the area is available.
 */
class PageBuilderAccess
{
    public static function allows(?Reseller $reseller): bool
    {
        if (! $reseller) {
            return false;
        }

        return $reseller->tierAllows('page_builder') || self::impersonatingSuperAdmin();
    }

    /** True when the current session is a super-admin impersonating a reseller. */
    public static function impersonatingSuperAdmin(): bool
    {
        $impersonatorId = session('impersonator_id');

        if (! $impersonatorId) {
            return false;
        }

        return (bool) User::find($impersonatorId)?->hasRole('super-admin');
    }
}
