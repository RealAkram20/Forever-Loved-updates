<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\SiteShareMetaHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('pages.auth.signin', [
            'title' => 'Sign In',
            'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                'Sign In',
                'login',
                [],
                'Sign in to manage memorials, tributes, and your account.'
            ),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // A reseller's staff or client is sent to their own reseller's space, not the platform
        // dashboard — even when they signed in from the platform login. See PostAuthRedirect.
        return redirect()->intended(\App\Support\PostAuthRedirect::url($request->user()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Signing out while impersonating a reseller means "stop being them", not "log
        // me out of everything" — the admin pressing it still has their own session
        // underneath, and destroying it would sign out the person who is actually at
        // the keyboard. Restore the admin instead, exactly as Return to Admin does.
        $impersonatorId = $request->session()->pull('impersonator_id');
        if ($impersonatorId && ($admin = \App\Models\User::find($impersonatorId))) {
            Auth::login($admin);
            $request->session()->regenerate();

            return redirect()->route('settings.resellers')
                ->with('success', 'Signed out of the reseller account — you are back on your own.');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
