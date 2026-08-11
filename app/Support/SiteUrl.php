<?php

namespace App\Support;

use App\Helpers\ThemeSetting;

/**
 * An address on the site currently being served.
 *
 * url() and route() cannot answer this, and — worse than failing — quietly answer with the
 * platform's. AppServiceProvider calls URL::forceRootUrl(config('app.url')) so subdirectory
 * installs work, which roots every generated path at our own address. On a reseller's host,
 * and under the /r/{slug} fallback, that turns every generated link on their white-labeled
 * site into a link back to us. ResolveResellerByHost documents the same trap for
 * redirect('/'); this is the general form of it.
 *
 * siteTenant() rather than tenant(), for the reason given there: an address follows whose
 * site it is, never who is looking at it. A reseller's own staff browsing the platform must
 * still be given the platform's links.
 */
class SiteUrl
{
    public static function to(string $path): string
    {
        $path = ltrim($path, '/');
        $reseller = ThemeSetting::siteTenant();

        if ($reseller) {
            // publicBaseUrl() already picks the verified custom domain over the subdomain,
            // and degrades to /r/{slug} where neither can be served.
            return $path === ''
                ? $reseller->publicBaseUrl()
                : $reseller->publicUrlForSlug($path);
        }

        return url('/'.$path);
    }
}
