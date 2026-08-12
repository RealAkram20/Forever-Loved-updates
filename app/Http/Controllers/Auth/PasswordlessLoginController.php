<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\BrandingHelper;
use App\Helpers\SiteShareMetaHelper;
use App\Http\Controllers\Controller;
use App\Models\LoginCode;
use App\Models\User;
use App\Services\SystemMailConfigurator;
use App\Support\PostAuthRedirect;
use App\Support\ReturnTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PasswordlessLoginController extends Controller
{
    /**
     * Show the email form (step 1).
     */
    public function showEmailForm(Request $request)
    {
        // A memorial page sending someone here appends ?return=; verifyCode() ends on
        // redirect()->intended(), so seeding it now is what brings them back to the
        // memorial — and whatever they were saying on it — instead of the dashboard.
        ReturnTo::seedIntended($request);

        return view('auth.passwordless-login', ['step' => 'email']);
    }

    /**
     * Send login code to email (step 1 submit).
     */
    public function sendCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower($validated['email']);
        $user = User::where('email', $email)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No account found with this email. Please create an account first.']);
        }

        $loginCode = LoginCode::generate($email);

        SystemMailConfigurator::applyFromSettings();

        if (! SystemMailConfigurator::mailDeliveryConfigured()) {
            return back()->withErrors(['email' => 'Email login codes are not available because outgoing mail is not configured. Please use password login or contact support.']);
        }

        // The name of the site this person belongs to, not the platform's: a reseller's
        // client signing in on a white-labeled site gets a code email that reads as that
        // site. User-keyed first (works wherever they typed their email), the served
        // site's name as fallback for accounts that belong to nobody yet.
        $siteName = BrandingHelper::displayNameFor($user->reseller);

        try {
            Mail::raw(
                "Your {$siteName} login code is: {$loginCode->code}\n\nThis code expires in 15 minutes.\n\nIf you didn't request this, please ignore this email.",
                function ($message) use ($email, $siteName, $user) {
                    $message->to($email)
                        ->subject("Your login code - {$siteName}")
                        ->from(config('mail.from.address'), $siteName);
                    if ($user->reseller?->contact_email) {
                        $message->replyTo($user->reseller->contact_email);
                    }
                }
            );
        } catch (\Exception $e) {
            report($e);

            return back()->withErrors(['email' => 'Failed to send code. Please try again.']);
        }

        return redirect()->route('login.code')->with('email', $email);
    }

    /**
     * Show the code entry form (step 2).
     */
    public function showCodeForm(Request $request)
    {
        $email = session('email');
        if (! $email) {
            return redirect()->route('login');
        }

        return view('auth.passwordless-login', [
            'title' => 'Enter login code',
            'step' => 'code',
            'email' => $email,
            'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                'Enter login code',
                'login.code',
                [],
                'Enter the verification code we sent to your email to complete sign-in.'
            ),
        ]);
    }

    /**
     * Verify code and log in (step 2 submit).
     */
    public function verifyCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $email = strtolower($validated['email']);
        $code = $validated['code'];

        // Per-address, not just per-IP. The route throttle counts requests from one client;
        // a six-digit code guessed from a pool of addresses is exactly the attack that gets
        // past it. Mirrors how LoginRequest keys its limiter on email|ip.
        $throttleKey = 'login-code:'.$email;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', [
                    'seconds' => $seconds = RateLimiter::availableIn($throttleKey),
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $loginCode = LoginCode::where('email', $email)
            ->where('code', $code)
            ->latest()
            ->first();

        if (! $loginCode || ! $loginCode->isValid()) {
            RateLimiter::hit($throttleKey, 900);

            throw ValidationException::withMessages([
                'code' => 'Invalid or expired code. Please try again.',
            ]);
        }

        $loginCode->markUsed();
        RateLimiter::clear($throttleKey);

        $user = User::where('email', $email)->firstOrFail();

        // Always remembered, like the password and Google doors. Proving you hold the
        // mailbox is the strongest sign-in this app has; it would be absurd for it to be
        // the one that expires in two hours.
        Auth::login($user, true);

        // Without this the pre-login session id survives authentication, so anyone who could
        // plant that id holds an authenticated session afterwards. The password login path
        // has always regenerated here; this one did not.
        $request->session()->regenerate();

        return redirect()->intended(PostAuthRedirect::url($user));
    }
}
