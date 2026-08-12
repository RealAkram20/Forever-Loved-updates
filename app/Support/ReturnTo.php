<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Puts a visitor back where they were after signing in.
 *
 * Every sign-in door ends on `redirect()->intended(PostAuthRedirect::url($user))`, which
 * reads `url.intended` from the session when something has put it there. Until now almost
 * nothing did: a visitor sent from a memorial page to the code login signed in successfully
 * and landed on the dashboard, with the memorial — and whatever they were in the middle of
 * saying on it — gone. For the people this app serves, that is the moment they give up.
 *
 * So the sign-in pages accept `?return=` and seed the intended URL from it, through here.
 *
 * Only a relative path is accepted — it must start with a single `/`. That is the entire
 * open-redirect defence, and it is sufficient: a relative target can only ever resolve on
 * the host the visitor is already on, whether that is the platform or a reseller's domain.
 * `//host` (protocol-relative) and backslash variants are refused, not normalised.
 */
class ReturnTo
{
    public const PARAM = 'return';

    public static function seedIntended(Request $request): void
    {
        $target = (string) $request->query(self::PARAM, '');

        if (self::isSafe($target)) {
            $request->session()->put('url.intended', $target);
        }
    }

    public static function isSafe(string $target): bool
    {
        return $target !== ''
            && str_starts_with($target, '/')
            && ! str_starts_with($target, '//')
            && ! str_contains($target, '\\');
    }
}
