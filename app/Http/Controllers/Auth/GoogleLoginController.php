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
    public function redirect(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (! SocialLoginHelper::googleLoginEnabled()) {
            abort(404);
        }

        $this->applyGoogleConfig();

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! SocialLoginHelper::googleLoginEnabled()) {
            abort(404);
        }

        $this->applyGoogleConfig();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
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
