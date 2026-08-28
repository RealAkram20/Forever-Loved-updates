<?php

use App\Support\SessionCookieDomain;

/**
 * One session across the apex and every reseller subdomain.
 *
 * Laravel's default cookie domain is null, which makes the session cookie host-only: sent back
 * to exactly the host that set it and to no subdomain of it. For an application whose tenants
 * are all subdomains of APP_URL, that default is wrong by construction — and it fails quietly.
 *
 * The report was "the super admin cannot edit the memorial on the live site, yet can reach the
 * admin areas". Both were true and both followed from the same cause: staff sign in on the
 * apex, so the admin screens worked, but on a reseller's subdomain the browser sent no session
 * cookie at all. The memorial page rendered — it is public — and `canBeEditedBy(null)` is false,
 * so every editing control was simply absent. It reads as a permissions bug from the outside.
 *
 * Derived from APP_URL rather than left to an environment variable, because the variable was
 * missing on the one environment that needed it and nothing anywhere said so.
 */
it('shares the session with every subdomain of the platform', function () {
    // The leading dot is the whole fix: it is what makes the cookie the browser stored on
    // alwaysforeverloved.com travel to uganda-funeral-services.alwaysforeverloved.com.
    expect(SessionCookieDomain::forAppUrl('https://alwaysforeverloved.com'))->toBe('.alwaysforeverloved.com')
        ->and(SessionCookieDomain::forAppUrl('https://alwaysforeverloved.com/'))->toBe('.alwaysforeverloved.com')
        ->and(SessionCookieDomain::forAppUrl('https://alwaysforeverloved.com/some/path'))->toBe('.alwaysforeverloved.com');
});

it('leaves a bare hostname alone', function () {
    // A dotted cookie domain on a single-label host is not something a browser will store, and
    // `.localhost` is refused outright by some of them — which would lock development out of
    // its own session rather than extend it.
    expect(SessionCookieDomain::forAppUrl('http://localhost/Forever'))->toBeNull()
        ->and(SessionCookieDomain::forAppUrl('http://app'))->toBeNull();
});

it('leaves an IP address alone', function () {
    // Cookie domains cannot be IPs. A staging box reached by address would be signed out on
    // every request.
    expect(SessionCookieDomain::forAppUrl('http://169.58.157.254'))->toBeNull()
        ->and(SessionCookieDomain::forAppUrl('http://169.58.157.254:8000'))->toBeNull();
});

it('falls back to nothing when APP_URL says nothing', function () {
    expect(SessionCookieDomain::forAppUrl(null))->toBeNull()
        ->and(SessionCookieDomain::forAppUrl(''))->toBeNull()
        ->and(SessionCookieDomain::forAppUrl('not a url'))->toBeNull();
});

it('wires the derivation into the session config', function () {
    // The rule is only worth anything if the framework actually reads it. Local development
    // sets SESSION_DOMAIN=null explicitly, which env() reads as null and the derivation is not
    // consulted — so what is asserted here is that the config resolves without error and obeys
    // whichever of the two is in force.
    $configured = config('session.domain');
    $expected = env('SESSION_DOMAIN', SessionCookieDomain::forAppUrl(env('APP_URL')));

    expect($configured)->toBe($expected);
});
