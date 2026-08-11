<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Guards the "who is this?" step on the endpoints a signed-out visitor may post to.
 *
 * Tributes, comments and guest chapters all take a `guest_email`, and every one of them
 * resolved that address against the users table and adopted the match: `$userId =
 * $existingUser->id`. Typing a registered member's address was therefore enough to have your
 * words published under their real name and profile photo — including an admin's.
 *
 * storeReaction() already refused this, returning `requires_login`, and the memorial page's
 * JS already knows how to handle that response by sending the visitor to the sign-in code
 * screen. This puts every other guest write on the same footing.
 *
 * Creating an account for a genuinely unknown address is left alone: that is the existing
 * "leave a tribute, get an account" onboarding, and it hands nothing to the sender that
 * they did not already control.
 */
class GuestIdentity
{
    /**
     * Is this address already a registered account? Only meaningful for a request with no
     * authenticated user — a signed-in caller is already the person they claim to be.
     */
    public static function isRegistered(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        if ($email === '') {
            return false;
        }

        return User::where('email', $email)->exists();
    }

    /**
     * The 422 to return when a guest claims a registered address. Shape matches
     * storeReaction()'s existing response so the front end needs no new branch.
     */
    public static function requiresLoginResponse(string $action = 'continue'): JsonResponse
    {
        return response()->json([
            'error' => "An account already uses this email. Please sign in to {$action}.",
            'requires_login' => true,
        ], 422);
    }
}
