<?php

namespace App\Support;

use App\Helpers\ThemeSetting;

/**
 * The correct login / register URLs for the current context, so a reseller's clients sign in
 * and register inside the reseller's own space instead of being bounced to the platform site.
 *
 * Three cases, one rule:
 *  - On a real reseller host (subdomain or verified custom domain) the shared named routes
 *    already resolve on that host — nothing special needed.
 *  - On the /r/{slug} path fallback (dev / subdirectory installs) there is no reseller host, so
 *    these point at the path-scoped auth routes that keep the tenant bound.
 *  - On the platform there is no tenant, so it is exactly the normal named route.
 *
 * Because the no-tenant and host-routing cases return the plain named route, the platform's own
 * auth pages are completely unaffected.
 */
class ResellerAuthUrls
{
    public static function login(): string
    {
        return self::resolve('login', 'login');
    }

    public static function register(): string
    {
        return self::resolve('register', 'register');
    }

    private static function resolve(string $routeName, string $pathSuffix): string
    {
        $tenant = ThemeSetting::tenant();

        if ($tenant && $tenant->usingFallbackAddress()) {
            return url('/r/'.$tenant->slug.'/'.$pathSuffix);
        }

        return route($routeName);
    }
}
