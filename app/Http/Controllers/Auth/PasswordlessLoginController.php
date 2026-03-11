<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordlessLoginController extends Controller
{
    /**
     * Show the email form (step 1).
     */
    public function showEmailForm()
    {
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

        if (!$user) {
            return back()->withErrors(['email' => 'No account found with this email. Please create an account first.']);
        }

        $loginCode = LoginCode::generate($email);

        try {
            Mail::raw(
                "Your Forever-Loved login code is: {$loginCode->code}\n\nThis code expires in 15 minutes.\n\nIf you didn't request this, please ignore this email.",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Your login code - Forever-Loved');
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
        if (!$email) {
            return redirect()->route('login');
        }

        return view('auth.passwordless-login', ['step' => 'code', 'email' => $email]);
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

        $loginCode = LoginCode::where('email', $email)
            ->where('code', $code)
            ->latest()
            ->first();

        if (!$loginCode || !$loginCode->isValid()) {
            throw ValidationException::withMessages([
                'code' => 'Invalid or expired code. Please try again.',
            ]);
        }

        $loginCode->markUsed();

        $user = User::where('email', $email)->firstOrFail();
        Auth::login($user, $request->boolean('remember'));

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
