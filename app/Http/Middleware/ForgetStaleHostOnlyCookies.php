<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Clears the host-only session cookies left behind when the cookie domain changed.
 *
 * Widening `session.domain` to `.example.com` does not replace the cookies a browser already
 * holds. A cookie is identified by (name, domain, path), so the old host-only one and the new
 * dotted one are two different cookies with the same name, and the browser sends both:
 *
 *     Cookie: forever-loved-session=<stale>; forever-loved-session=<current>
 *
 * PHP keeps the *first* of a repeated name. RFC 6265 orders same-path cookies by creation time,
 * oldest first — so the stale one wins every request. The session it names is gone, a fresh one
 * is started, and the CSRF token rendered into the page never matches the session that handles
 * the next write. Every POST answers "CSRF token mismatch", and a reload does not help because
 * the reload lands on a new session too. That is what a visitor saw when they tried to leave a
 * flower.
 *
 * The stale cookie is never refreshed again, so it dies on its own within one session lifetime.
 * This makes it die on the next request instead, because the window is measured in hours and
 * the people inside it are families who have no idea they are being asked to clear cookies.
 *
 * The deletion carries no Domain attribute, which is what makes it target the host-only cookie
 * and only that one: the dotted cookie is a different tuple and cannot be reached by it. And it
 * is sent only when the request actually carried a duplicate, so in ordinary operation this
 * middleware adds a header count and nothing else.
 *
 * Prepended to the group so it runs last on the way out, after EncryptCookies — an expired
 * cookie's value is irrelevant, but there is no reason to hand the browser an encrypted empty
 * string.
 *
 * Delete this once no browser can still be carrying one, which is one session lifetime after
 * the deploy that widened the domain.
 */
class ForgetStaleHostOnlyCookies
{
    /**
     * XSRF-TOKEN as well as the session: axios reads that cookie itself and sends it as
     * X-XSRF-TOKEN, and `document.cookie` returns duplicates in the same order, so a stale one
     * would 419 those requests for exactly the same reason.
     *
     * @return array<int, string>
     */
    private function names(): array
    {
        return [(string) config('session.cookie'), 'XSRF-TOKEN'];
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only where cookies are meant to be domain-scoped. Where session.domain is null the
        // cookies are host-only by design and a duplicate means something else entirely.
        $domain = config('session.domain');

        if (! is_string($domain) || $domain === '') {
            return $response;
        }

        $header = (string) $request->headers->get('Cookie', '');

        if ($header === '') {
            return $response;
        }

        foreach ($this->names() as $name) {
            if ($name === '' || $this->timesSent($header, $name) < 2) {
                continue;
            }

            $response->headers->setCookie(new Cookie(
                name: $name,
                value: '',
                expire: 1,
                path: (string) config('session.path', '/'),
                // The point of the whole exercise: no domain, so this reaches the host-only
                // copy and leaves the dotted one alone.
                domain: null,
                secure: $request->isSecure(),
                httpOnly: $name !== 'XSRF-TOKEN',
                raw: false,
                sameSite: config('session.same_site', 'lax'),
            ));
        }

        return $response;
    }

    /**
     * How many times a cookie name appears in the raw header.
     *
     * Read from the header rather than from $request->cookies, which is a map and has already
     * collapsed the duplicate — the collapsing is the bug, so the evidence only exists here.
     */
    private function timesSent(string $header, string $name): int
    {
        $count = 0;

        foreach (explode(';', $header) as $pair) {
            if (trim(explode('=', $pair, 2)[0]) === $name) {
                $count++;
            }
        }

        return $count;
    }
}
