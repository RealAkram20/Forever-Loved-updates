<?php

namespace App\Support;

use App\Models\User;

/**
 * Where a user lands right after authenticating — their "designated place".
 *
 * A user who belongs to a reseller (their staff, or a family the reseller onboarded) is sent
 * to that reseller's own site rather than the platform, no matter where they signed in from —
 * so a reseller's client who logs in on the main platform login still ends up on the
 * reseller's branded dashboard.
 *
 * On real host routing that means their subdomain or verified custom domain, where the
 * dashboard renders in the reseller's branding. In dev / subdirectory installs there is no
 * host-routed, tenant-scoped dashboard URL, so it falls back to the shared /dashboard (which
 * still role-routes correctly — it just wears the platform chrome in that environment).
 *
 * Note: a cross-host redirect only keeps the session if the session cookie is shared with the
 * target host (SESSION_DOMAIN covering the reseller subdomains). A separate custom domain has
 * its own cookie jar, so the primary flow is a client signing in on the reseller's own host in
 * the first place — which this keeps them on.
 */
class PostAuthRedirect
{
    public static function url(User $user): string
    {
        $reseller = $user->reseller;

        if ($reseller && $reseller->isActive() && ! $reseller->usingFallbackAddress()) {
            return rtrim($reseller->publicBaseUrl(), '/').'/dashboard';
        }

        return route('dashboard', absolute: false);
    }
}
