<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\SocialLoginHelper;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if ($flowRoute && \Illuminate\Support\Facades\Route::has($flowRoute)) {
            $request->session()->put('url.intended', route($flowRoute));
        }

        try {
            $this->applyGoogleConfig();

            return Socialite::driver('google')->redirect();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Google sign-in redirect failed', [
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
            \Illuminate\Support\Facades\Log::warning('Google sign-in callback failed', [
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

        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update(['google_id' => $googleId]);
            }
        }

        if (! $user) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => null,
                'google_id' => $googleId,
                'email_verified_at' => now(),
            ]);

            if (Role::where('name', 'user')->where('guard_name', 'web')->exists()) {
                $user->assignRole('user');
            }

            event(new Registered($user));
            NotificationService::notifyNewUserSignup($user);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
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
