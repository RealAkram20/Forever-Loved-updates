<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Who a signed-out visitor is, for the one purpose that actually needs to know: telling
 * two taps by the same person apart from taps by two people.
 *
 * The one-tap cards used to answer that question with an email address. A guest who lit a
 * candle had to type a name and an address into a modal first, the address was turned into
 * a real user account behind their back, and the tribute was keyed on that account's id —
 * which is the only reason "one candle per person" worked at all.
 *
 * That price was far too high for a gesture. It put a form in front of a tap, and it set a
 * trap: the account created by the tap made the *next* thing that person did on the page —
 * writing a few words to go with it — fail with "an account already uses this email".
 *
 * So a tap now asks for nothing. This is the whole identity a gesture needs: an opaque
 * value in a cookie, stored hashed on the row so the table is not a list of live cookies.
 * It says nothing about who anybody is, and it can say nothing, because it is generated
 * rather than collected.
 *
 * Deliberately issued on the write, never on a page view: someone who only reads a memorial
 * is never marked at all.
 */
class VisitorToken
{
    public const COOKIE = 'visitor_id';

    /** Where the token minted for this request is remembered, for the rest of this request. */
    private const ATTRIBUTE = 'visitor_token.minted';

    /**
     * Long enough that lighting a candle this year and another next year still counts once.
     * Browsers cap what they will actually keep (Chrome at 400 days); this is the ceiling,
     * not a promise.
     */
    private const LIFETIME_MINUTES = 60 * 24 * 365 * 5;

    /**
     * The token this browser already carries, if it carries one.
     */
    public static function current(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * The token for this browser, minting and queueing one if there is none.
     *
     * Two calls within one request hand back the same value rather than racing to set two
     * different cookies. That memo lives on the request, deliberately, and not on the cookie
     * jar: the jar is a singleton whose queue outlives a single request in any long-lived
     * container, so reading the token back out of it would eventually hand one visitor the
     * token minted for the visitor before them — which, for a dedupe key, means silently
     * swallowing their tribute as a duplicate of a stranger's.
     */
    public static function ensure(Request $request): string
    {
        if ($existing = self::current($request)) {
            return $existing;
        }

        if ($request->attributes->has(self::ATTRIBUTE)) {
            return (string) $request->attributes->get(self::ATTRIBUTE);
        }

        $token = (string) Str::uuid();
        $request->attributes->set(self::ATTRIBUTE, $token);

        Cookie::queue(Cookie::make(
            name: self::COOKIE,
            value: $token,
            minutes: self::LIFETIME_MINUTES,
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $token;
    }

    /**
     * What gets written to the row. Hashed, because the stored form is only ever compared
     * against a freshly hashed cookie — nothing needs to read it back, so nothing should be
     * able to.
     */
    public static function fingerprint(?string $token): ?string
    {
        return $token === null || $token === '' ? null : hash('sha256', $token);
    }

    /**
     * The fingerprint for this request's browser, issuing a cookie if needed. The one call
     * a write path normally wants.
     */
    public static function fingerprintFor(Request $request): string
    {
        return self::fingerprint(self::ensure($request));
    }
}
