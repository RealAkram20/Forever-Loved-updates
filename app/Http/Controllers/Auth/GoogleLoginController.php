<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\SocialLoginHelper;
use App\Helpers\ThemeSetting;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\PostAuthRedirect;
use App\Support\ReturnTo;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class GoogleLoginController extends Controller
{
    /**
     * Where a `flow` may send the user back to after Google returns. A closed
     * list, not a free-form URL: a caller-supplied redirect target is an open
     * redirect, and this route is reachable by anyone.
     */
    private const FLOW_DESTINATIONS = [
        'memorial-signup' => 'memorial.create.step3',
    ];

    public function redirect(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (! SocialLoginHelper::googleLoginEnabled()) {
            abort(404);
        }

        // callback() ends on redirect()->intended(), so a known flow just seeds
        // the intended URL before we hand off to Google.
        $flowRoute = self::FLOW_DESTINATIONS[(string) $request->query('flow')] ?? null;
        if ($flowRoute && Route::has($flowRoute)) {
            $request->session()->put('url.intended', route($flowRoute));
        }

        // ?return=/some-memorial, relative-path-only — same open-redirect posture as the
        // flow allowlist above, just for "back to the page that sent you" rather than a
        // named step. Lets a visitor who chose Google mid-memorial land back on the
        // memorial instead of the dashboard.
        ReturnTo::seedIntended($request);

        try {
            $this->applyGoogleConfig();

            return Socialite::driver('google')->redirect();
        } catch (\Throwable $e) {
            Log::error('Google sign-in redirect failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'redirect_uri' => SocialLoginHelper::googleCallbackUrl(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'Could not start Google sign-in. Re-save the Client Secret in Admin → Settings → General, and confirm the Authorized redirect URI in Google Cloud is exactly: '.SocialLoginHelper::googleCallbackUrl(),
            ]);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! SocialLoginHelper::googleLoginEnabled()) {
            abort(404);
        }

        $this->applyGoogleConfig();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google sign-in callback failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'redirect_uri' => SocialLoginHelper::googleCallbackUrl(),
                'has_code' => $request->has('code'),
                'oauth_error' => $request->query('error'),
            ]);

            return redirect()->route('login')
                ->withErrors(['email' => 'Google sign-in was cancelled or failed. Try again or use email.']);
        }

        $email = $googleUser->getEmail();
        if (! $email) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your Google account did not provide an email. Use another sign-in method.']);
        }

        $email = strtolower($email);
        $googleId = (string) $googleUser->getId();
        $name = $googleUser->getName()
            ?: ($googleUser->getNickname() ?: (explode('@', $email)[0] ?? 'User'));

        $user = $this->resolveOrCreateUser($email, $googleId, $name);

        Auth::login($user, true);
        $request->session()->regenerate();

        // Through PostAuthRedirect like every other sign-in: a reseller's client lands
        // on their reseller's branded dashboard, not wherever this host happens to be.
        return redirect()->intended(PostAuthRedirect::url($user));
    }

    /**
     * Google One Tap — the corner prompt. The page's JS hands over the credential Google
     * gave it (a signed ID token) and this verifies it with Google and signs the visitor
     * in, in place: no redirect round trip, no form. The caller reloads the page.
     *
     * Verification goes to Google's tokeninfo endpoint rather than a local JWT decode: it
     * checks the signature, expiry and issuer in one call, with no new dependency. What
     * remains ours to check is that the token was minted for OUR client id — without the
     * `aud` check, any site's Google token would sign its holder into this one.
     */
    public function oneTap(Request $request): JsonResponse
    {
        if (! SocialLoginHelper::googleLoginEnabled()) {
            abort(404);
        }

        $validated = $request->validate(['credential' => ['required', 'string']]);

        $clientId = trim((string) (SystemSetting::get('oauth.google_client_id', '') ?: env('GOOGLE_CLIENT_ID', '')));

        $response = Http::timeout(10)
            ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $validated['credential']]);

        $claims = $response->json() ?? [];
        $issuerOk = in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
        $audienceOk = ($claims['aud'] ?? null) === $clientId && $clientId !== '';
        $emailOk = ! empty($claims['email']) && in_array($claims['email_verified'] ?? false, [true, 'true', 1, '1'], true);

        if (! $response->ok() || ! $issuerOk || ! $audienceOk || ! $emailOk) {
            return response()->json(['error' => 'Google sign-in could not be verified.'], 422);
        }

        $email = strtolower($claims['email']);
        $user = $this->resolveOrCreateUser(
            $email,
            (string) ($claims['sub'] ?? ''),
            trim((string) ($claims['name'] ?? '')) ?: (explode('@', $email)[0] ?? 'User'),
        );

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json(['success' => true]);
    }

    /**
     * The account behind a verified Google identity — matched by google_id, then by email
     * (adopting the google_id onto an existing account), created if neither exists. Shared
     * by the OAuth redirect flow and One Tap so the two doors can never resolve the same
     * person differently.
     */
    private function resolveOrCreateUser(string $email, string $googleId, string $name): User
    {
        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update(['google_id' => $googleId]);
            }
        }

        if (! $user) {
            // First Google sign-in on a reseller host makes them that reseller's client,
            // exactly as the register form does.
            $reseller = ThemeSetting::tenant();

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => null,
                'google_id' => $googleId,
                'email_verified_at' => now(),
                'reseller_id' => $reseller?->id,
                'original_reseller_id' => $reseller?->id,
            ]);

            if (Role::where('name', 'user')->where('guard_name', 'web')->exists()) {
                $user->assignRole('user');
            }

            event(new Registered($user));
            NotificationService::notifyNewUserSignup($user);
        }

        return $user;
    }

    private function applyGoogleConfig(): void
    {
        $clientId = trim((string) (SystemSetting::get('oauth.google_client_id', '') ?: env('GOOGLE_CLIENT_ID', '')));
        $clientSecret = trim((string) (SystemSetting::get('oauth.google_client_secret', '') ?: env('GOOGLE_CLIENT_SECRET', '')));

        config([
            'services.google.client_id' => $clientId,
            'services.google.client_secret' => $clientSecret,
            'services.google.redirect' => SocialLoginHelper::googleCallbackUrl(),
        ]);
    }
}
