<?php

namespace App\Support;

use App\Helpers\BrandingHelper;
use App\Helpers\SocialLoginHelper;
use App\Jobs\SendRawEmail;
use App\Models\Memorial;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

/**
 * A guest's first write is their signup — visibly, and with the session to show for it.
 *
 * The account-creating write paths (a story from the composer, a heart on a post) used to
 * mint the User row and then leave the visitor exactly as signed-out as before: their very
 * next action asked for name and email again, and — worse — the address they had just
 * "registered" now failed the registered-address check. The account existed only as a trap.
 *
 * This makes the write behave like the signup it already was: the account is created, the
 * visitor is signed in on the spot with the same long-lived remember cookie every other
 * sign-in door leaves, and the welcome email says plainly what happened and how to get back
 * in from any other device — a one-time code, a password if they want one, Google if it's
 * enabled. On this browser they are simply never asked again.
 */
class GuestOnboarding
{
    /**
     * Create the account for a write by `$name <$email>` on `$memorial`, and sign the
     * request's session into it. Caller has already established the address is unregistered
     * (the GuestIdentity check) — this does not re-check.
     *
     * The session id is rotated, which also rotates the CSRF token; callers must hand the
     * fresh token back to the page (`csrf_token()`) or every later request from it 419s.
     */
    public static function signUpAndIn(Request $request, Memorial $memorial, string $name, string $email): User
    {
        // The memorial's reseller owns this relationship: a visitor moved to write on a
        // reseller's memorial becomes that reseller's user, not a stray platform account.
        $user = User::create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => null,
            'reseller_id' => $memorial->reseller_id,
            'original_reseller_id' => $memorial->reseller_id,
        ]);

        // The same role every other door assigns; the guest paths skipping it was an
        // accident of history, not a decision.
        if (Role::where('name', 'user')->where('guard_name', 'web')->exists()) {
            $user->assignRole('user');
        }

        NotificationService::notifyNewUserSignup($user);
        self::sendWelcomeEmail($memorial, $user);

        // Signed in for good, like every other door. Rotating the session id here is the
        // same fixation defence the login forms perform — without it, a session id planted
        // before this write would be authenticated by it.
        Auth::login($user, true);
        $request->session()->regenerate();

        return $user;
    }

    /**
     * Branded as the site the visitor was actually on — this lands minutes after they used
     * a white-labeled page, and a platform-branded mail there reads as phishing.
     */
    private static function sendWelcomeEmail(Memorial $memorial, User $user): void
    {
        $siteName = BrandingHelper::displayNameFor($memorial->reseller);

        $lines = [
            "Welcome to {$siteName}!",
            '',
            "You shared something on {$memorial->full_name}'s memorial, so we created your account — your words stay yours, and you are already signed in on the device you used.",
            '',
            'From any other device, getting back in is easy:',
            '- Sign in with a one-time code (no password needed): '.route('login.passwordless'),
            '- Or set a password if you prefer one: '.route('password.request'),
        ];

        if (SocialLoginHelper::googleLoginEnabled()) {
            $lines[] = '- Or simply continue with Google using this email address.';
        }

        ReliableDispatch::dispatch(new SendRawEmail(
            to: $user->email,
            name: $user->name,
            subject: "Welcome to {$siteName} — your account is ready",
            body: implode("\n", $lines),
            fromName: $siteName,
            replyTo: $memorial->reseller?->contact_email,
        ));
    }
}
