<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\SiteShareMetaHelper;
use App\Helpers\ThemeSetting;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\PostAuthRedirect;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('pages.auth.signup', [
            'title' => 'Sign Up',
            'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                'Sign Up',
                'register',
                [],
                'Create an account to build and manage online memorials for your loved ones.'
            ),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Registering on a reseller's site (subdomain, custom domain, or /r/{slug}) makes the
        // new account that reseller's client, not a platform user — the white-label whole point.
        // The tenant is whatever the host / path middleware bound for this request.
        $reseller = ThemeSetting::tenant();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'reseller_id' => $reseller?->id,
            'original_reseller_id' => $reseller?->id,
        ]);

        // A reseller's client gets the plain 'user' role, matching how the reseller's own
        // "add client" flow onboards families.
        if ($reseller) {
            $user->assignRole('user');
        }

        event(new Registered($user));

        NotificationService::notifyNewUserSignup($user);

        Auth::login($user);

        return redirect(PostAuthRedirect::url($user));
    }
}
