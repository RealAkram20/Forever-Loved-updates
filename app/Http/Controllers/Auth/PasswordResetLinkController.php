<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\SystemMailConfigurator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        SystemMailConfigurator::applyFromSettings();

        if (! SystemMailConfigurator::mailDeliveryConfigured()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Password reset email is not available because outgoing mail is not configured. Please contact the site administrator.']);
        }

        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'We could not send the reset link. Please try again later.']);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
