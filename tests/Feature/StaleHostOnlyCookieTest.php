<?php

use Illuminate\Support\Facades\Config;

/**
 * The cookie that made every write answer "CSRF token mismatch".
 *
 * Widening `session.domain` to `.example.com` does not replace what a browser already holds. A
 * cookie is identified by (name, domain, path), so the old host-only one and the new dotted one
 * are two different cookies sharing a name, and the browser sends both. PHP keeps the *first*
 * of a repeated name, and RFC 6265 orders same-path cookies oldest-first — so the dead one won
 * every request. A fresh session was started each time, the token rendered into the page never
 * matched the session that handled the next write, and reloading did not help because the
 * reload landed on a new session too.
 *
 * Proven against the live site before this was written: replaying one request with
 * `session=stale; session=good` returned a different CSRF token than `session=good` alone,
 * while `session=good; session=stale` returned the same one.
 */
function cookieHeader(string $raw): array
{
    return ['Cookie' => $raw];
}

/** The Set-Cookie lines this response would send for $name. */
function setCookiesNamed($response, string $name): array
{
    return collect($response->headers->getCookies())
        ->filter(fn ($c) => $c->getName() === $name)
        ->values()
        ->all();
}

it('expires the host-only copy when a request carries two of the same cookie', function () {
    Config::set('session.domain', '.example.com');

    $name = config('session.cookie');

    $response = $this->withHeaders(cookieHeader("{$name}=stale; {$name}=current"))->get('/');

    $forgotten = collect(setCookiesNamed($response, $name))
        ->first(fn ($c) => $c->getExpiresTime() > 0 && $c->getExpiresTime() < time());

    expect($forgotten)->not->toBeNull('a duplicate should be cleared, not left to win every request')
        // No Domain attribute is the whole mechanism: it reaches the host-only cookie and
        // cannot touch the dotted one, which is a different tuple.
        ->and($forgotten->getDomain())->toBeNull();
});

it('leaves a request with one cookie completely alone', function () {
    Config::set('session.domain', '.example.com');

    $name = config('session.cookie');

    $response = $this->withHeaders(cookieHeader("{$name}=current"))->get('/');

    $expired = collect(setCookiesNamed($response, $name))
        ->filter(fn ($c) => $c->getExpiresTime() > 0 && $c->getExpiresTime() < time());

    expect($expired)->toBeEmpty('ordinary traffic must not be handed a deletion for its own session');
});

it('does nothing where cookies are host-only by design', function () {
    // Local development sets session.domain to null, so cookies are host-only and a repeat
    // means something this middleware has no business guessing about.
    Config::set('session.domain', null);

    $name = config('session.cookie');

    $response = $this->withHeaders(cookieHeader("{$name}=stale; {$name}=current"))->get('/');

    $expired = collect(setCookiesNamed($response, $name))
        ->filter(fn ($c) => $c->getExpiresTime() > 0 && $c->getExpiresTime() < time());

    expect($expired)->toBeEmpty();
});

it('clears a duplicated XSRF-TOKEN too', function () {
    // axios reads that cookie itself and sends it as X-XSRF-TOKEN, and document.cookie returns
    // duplicates in the same order — so a stale one 419s those requests for the same reason.
    Config::set('session.domain', '.example.com');

    $response = $this->withHeaders(cookieHeader('XSRF-TOKEN=stale; XSRF-TOKEN=current'))->get('/');

    $forgotten = collect(setCookiesNamed($response, 'XSRF-TOKEN'))
        ->first(fn ($c) => $c->getExpiresTime() > 0 && $c->getExpiresTime() < time());

    expect($forgotten)->not->toBeNull()
        ->and($forgotten->getDomain())->toBeNull()
        // Readable by script, like the cookie it is replacing — a httpOnly deletion is still a
        // deletion, but there is no reason for the two to disagree.
        ->and($forgotten->isHttpOnly())->toBeFalse();
});
