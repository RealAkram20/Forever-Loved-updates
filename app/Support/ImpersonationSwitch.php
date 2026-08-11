<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The one correct way to swap the authenticated user mid-session.
 *
 * AuthenticateSession pins every session to its user's password hash. A plain
 * Auth::login() + regenerate() leaves the *previous* user's hash pinned, and the
 * very next request logs the new user out for a mismatch they never caused — which
 * is exactly how "Return to Admin" came to bounce admins to the login screen
 * whenever the reseller owner they impersonated was passwordless. Every switch has
 * to re-pin the hash to the user it now belongs to.
 */
class ImpersonationSwitch
{
    public static function to(Request $request, User $user, bool $remember = false): void
    {
        Auth::login($user, $remember);

        // Rotate the id so the swap can't ride an old cookie; keep the data — the
        // impersonation marker, the flash message, the cart of whoever this is.
        $request->session()->regenerate();

        $request->session()->put(
            'password_hash_'.config('auth.defaults.guard', 'web'),
            $user->getAuthPassword()
        );
    }
}
