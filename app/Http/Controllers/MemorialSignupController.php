<?php

namespace App\Http\Controllers;

use App\Models\Memorial;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\TemplateBioGeneratorService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class MemorialSignupController extends Controller
{
    private const SESSION_KEY = 'memorial_signup';

    /**
     * Step 1: Deceased details.
     */
    public function step1(Request $request)
    {
        $data = session(self::SESSION_KEY, []);
        return view('pages.memorial-signup.step1', [
            'title' => 'Create Memorial - Step 1',
            'data' => $data,
        ]);
    }

    public function storeStep1(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'relationship' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'primary_profession' => ['nullable', 'string', 'max:255'],
            'major_achievements' => ['nullable', 'string', 'max:2000'],
            'date_of_birth' => ['nullable', 'date'],
            'date_of_passing' => ['nullable', 'date'],
            'birth_city' => ['nullable', 'string', 'max:255'],
            'birth_state' => ['nullable', 'string', 'max:255'],
            'birth_country' => ['nullable', 'string', 'max:255'],
            'death_city' => ['nullable', 'string', 'max:255'],
            'death_state' => ['nullable', 'string', 'max:255'],
            'death_country' => ['nullable', 'string', 'max:255'],
            'cause_of_death' => ['nullable', 'string', 'max:255'],
            'cause_of_death_private' => ['nullable'],
        ]);

        $validated['cause_of_death_private'] = $request->boolean('cause_of_death_private');

        session([self::SESSION_KEY => array_merge(session(self::SESSION_KEY, []), $validated)]);

        if ($request->user()) {
            return redirect()->route('memorial.create.step3');
        }
        return redirect()->route('memorial.create.step2');
    }

    /**
     * Step 2: Account (register or sign in).
     */
    public function step2(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('memorial.create.step3');
        }
        $data = session(self::SESSION_KEY, []);
        if (empty($data['first_name'])) {
            return redirect()->route('memorial.create.step1')
                ->with('error', 'Please complete Step 1 first.');
        }
        return view('pages.memorial-signup.step2', [
            'title' => 'Create Memorial - Create Account',
            'data' => $data,
        ]);
    }

    /**
     * AJAX: Check if email exists.
     */
    public function checkEmail(Request $request)
    {
        $email = $request->input('email') ?? $request->json('email');
        $exists = $email ? User::where('email', $email)->exists() : false;
        return response()->json(['exists' => $exists]);
    }

    /**
     * Step 2: Register new account.
     */
    public function storeStep2Register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('memorial.create.step3');
    }

    /**
     * Step 2: Sign in existing account.
     */
    public function storeStep2Login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->route('memorial.create.step3');
        }

        return back()->withErrors(['email' => 'The provided credentials do not match our records.']);
    }

    /**
     * Step 3: Plan selection.
     */
    public function step3(Request $request)
    {
        if (!$request->user()) {
            return redirect()->route('memorial.create.step2');
        }
        $data = session(self::SESSION_KEY, []);
        if (empty($data['first_name'])) {
            return redirect()->route('memorial.create.step1');
        }
        $plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
        return view('pages.memorial-signup.step3', [
            'title' => 'Create Memorial - Choose Plan',
            'data' => $data,
            'plans' => $plans,
        ]);
    }

    public function storeStep3(Request $request)
    {
        if (!$request->user()) {
            return redirect()->route('memorial.create.step2');
        }
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
        ]);
        session([self::SESSION_KEY => array_merge(session(self::SESSION_KEY, []), $validated)]);
        return redirect()->route('memorial.create.complete');
    }

    /**
     * Complete: Create memorial with defaults (privacy/setup can be done later).
     */
    public function complete(Request $request)
    {
        if (!$request->user()) {
            return redirect()->route('memorial.create.step2');
        }
        $data = session(self::SESSION_KEY, []);
        if (empty($data['first_name']) || empty($data['plan_id'])) {
            return redirect()->route('memorial.create.step1');
        }

        $fullName = trim(implode(' ', array_filter([
            $data['first_name'],
            $data['middle_name'] ?? '',
            $data['last_name'],
        ])));

        $memorial = Memorial::create([
            'user_id' => $request->user()->id,
            'slug' => $this->generateSlug($fullName),
            'title' => 'In Loving Memory of ' . $fullName,
            'full_name' => $fullName,
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? null,
            'last_name' => $data['last_name'],
            'gender' => $data['gender'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'short_description' => $data['short_description'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'primary_profession' => $data['primary_profession'] ?? null,
            'major_achievements' => $data['major_achievements'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'date_of_passing' => $data['date_of_passing'] ?? null,
            'birth_city' => $data['birth_city'] ?? null,
            'birth_state' => $data['birth_state'] ?? null,
            'birth_country' => $data['birth_country'] ?? null,
            'death_city' => $data['death_city'] ?? null,
            'death_state' => $data['death_state'] ?? null,
            'death_country' => $data['death_country'] ?? null,
            'cause_of_death' => $data['cause_of_death'] ?? null,
            'designation' => (!empty($data['cause_of_death']) && $data['cause_of_death'] !== 'No designation') ? $data['cause_of_death'] : null,
            'cause_of_death_private' => $data['cause_of_death_private'] ?? false,
            'biography' => null,
            'theme' => 'free',
            'plan' => 'free',
            'completion_status' => 'pending',
            'is_public' => true,
        ]);

        try {
            $bioService = app(TemplateBioGeneratorService::class);
            $biography = $bioService->generateStructured($memorial);
            if ($biography && trim($biography) !== '') {
                $memorial->update(['biography' => $biography]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        session()->forget(self::SESSION_KEY);

        return redirect()->route('memorial.create.preparing', ['slug' => $memorial->slug]);
    }

    /**
     * Preparing: Gentle preloader shown while memorial is finalized.
     */
    public function preparing(string $slug)
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (auth()->id() !== $memorial->user_id) {
            return redirect()->route('memorial.public', ['slug' => $slug]);
        }

        return view('pages.memorial-signup.preparing', [
            'title' => 'Preparing Memorial',
            'memorial' => $memorial,
        ]);
    }

    private function generateSlug(string $fullName): string
    {
        $baseSlug = Str::slug($fullName);
        $slug = $baseSlug;
        $suffix = 0;
        while (Memorial::where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $baseSlug . '-' . $suffix;
        }
        return $slug;
    }
}
