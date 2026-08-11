<?php

namespace App\Http\Controllers;

use App\Helpers\SiteShareMetaHelper;
use App\Models\Memorial;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\NotificationService;
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

    // Set after preparePaidCheckout creates the memorial (the wizard session is
    // cleared at that point) so a failed payment attempt can resume checkout
    // for the same memorial instead of dead-ending on "complete Step 1 first".
    private const RESUME_KEY = 'memorial_signup_resume_memorial_id';

    /**
     * Step 1: Deceased details.
     */
    public function step1(Request $request)
    {
        $data = session(self::SESSION_KEY, []);

        return view('pages.memorial-signup.step1', [
            'title' => 'Create Memorial - Step 1',
            'data' => $data,
            'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                'Create a memorial',
                'memorial.create.step1',
                [],
                'Start a beautiful online memorial: enter details about your loved one to begin.'
            ),
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
            'relationship_other' => ['nullable', 'string', 'max:255'],
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
        ]);

        $validated['relationship'] = Memorial::resolveRelationship(
            $validated['relationship'] ?? null,
            $validated['relationship_other'] ?? null
        );
        unset($validated['relationship_other']);

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
            'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                'Create your account',
                'memorial.create.step2',
                [],
                'Sign up or sign in to continue creating your memorial.'
            ),
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // The wizard is the main conversion funnel, and it runs on reseller hosts too:
        // whoever registers here on a white-labeled site becomes THAT reseller's client,
        // exactly as RegisteredUserController already does. Without this, a reseller's
        // primary signup path quietly built the platform's user base instead of theirs —
        // and the memorial created two steps later inherited the wrong owner with it.
        $reseller = \App\Helpers\ThemeSetting::tenant();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'reseller_id' => $reseller?->id,
            'original_reseller_id' => $reseller?->id,
        ]);
        $user->assignRole('user');

        event(new Registered($user));
        NotificationService::notifyNewUserSignup($user);
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
        if (! $request->user()) {
            return redirect()->route('memorial.create.step2');
        }
        $data = session(self::SESSION_KEY, []);
        if (empty($data['first_name'])) {
            return redirect()->route('memorial.create.step1');
        }
        // sellableOnHost, not sellableTo: on a reseller's site the wizard must offer the
        // RESELLER's plans and prices regardless of who the visitor already is — a
        // platform-registered visitor on kangaruride.com was being shown our catalogue.
        $plans = SubscriptionPlan::where('is_active', true)->sellableOnHost(\App\Helpers\ThemeSetting::siteTenantId(), auth()->user())->orderBy('sort_order')->get();
        $currency = SystemSetting::get('payments.currency', 'USD');
        $paymentsEnabled = (bool) SystemSetting::get('payments.enabled', false);
        $pesapalEnabled = (bool) SystemSetting::get('payments.pesapal_enabled', false);

        return view('pages.memorial-signup.step3', [
            'title' => 'Create Memorial - Choose Plan',
            'data' => $data,
            'plans' => $plans,
            'currency' => $currency,
            'paymentsEnabled' => $paymentsEnabled,
            'pesapalEnabled' => $pesapalEnabled,
            'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                'Choose a memorial plan',
                'memorial.create.step3',
                [],
                'Pick a free or paid plan to publish and customize your loved one’s memorial.'
            ),
        ]);
    }

    public function storeStep3(Request $request)
    {
        if (! $request->user()) {
            return redirect()->route('memorial.create.step2');
        }
        // Scoped to what this user may actually buy. A bare `exists` check let anyone
        // submit another tenant's plan id — and if that plan was free, claim its
        // entitlements outright.
        $validated = $request->validate([
            'plan_id' => [
                'required',
                Rule::exists('subscription_plans', 'id')->where(
                    fn ($q) => $request->user()->reseller_id
                        ? $q->where('reseller_id', $request->user()->reseller_id)
                        : $q->whereNull('reseller_id')
                ),
            ],
        ]);
        session([self::SESSION_KEY => array_merge(session(self::SESSION_KEY, []), $validated)]);

        return redirect()->route('memorial.create.complete');
    }

    /**
     * Prepare paid checkout: Create memorial and return JSON for checkout modal (used on Step 3).
     */
    public function preparePaidCheckout(Request $request)
    {
        if (! $request->user()) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }
        $data = session(self::SESSION_KEY, []);

        $planId = (int) ($request->input('plan_id') ?? $data['plan_id'] ?? 0);
        // Host-scoped, not find(): the id can come straight off the request here, and the
        // catalogue must match what step 3 offered on this host.
        $plan = SubscriptionPlan::sellableOnHost(\App\Helpers\ThemeSetting::siteTenantId(), $request->user())->find($planId);
        if (! $plan || $plan->isFree()) {
            return response()->json(['success' => false, 'error' => 'Please select a paid plan.'], 400);
        }

        if (empty($data['first_name'])) {
            $memorial = $this->resumableCheckoutMemorial($request);
            if (! $memorial) {
                return response()->json(['success' => false, 'error' => 'Please complete Step 1 first.'], 400);
            }
        } else {
            $memorial = $this->createMemorialFromSession($request->user(), $data, $plan);
            session()->forget(self::SESSION_KEY);
            session([self::RESUME_KEY => $memorial->id]);
        }

        return response()->json([
            'success' => true,
            'memorial_slug' => $memorial->slug,
            'plan_id' => $plan->id,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'interval' => $plan->interval,
            ],
        ]);
    }

    /**
     * The memorial created by an earlier preparePaidCheckout attempt whose
     * payment never completed, if the retry may resume checkout for it.
     */
    private function resumableCheckoutMemorial(Request $request): ?Memorial
    {
        $memorialId = session(self::RESUME_KEY);
        if (! $memorialId) {
            return null;
        }

        return Memorial::where('id', $memorialId)
            ->where('user_id', $request->user()->id)
            ->whereNull('user_subscription_id')
            ->first();
    }

    /**
     * Complete: Create memorial with defaults (privacy/setup can be done later).
     */
    public function complete(Request $request)
    {
        if (! $request->user()) {
            return redirect()->route('memorial.create.step2');
        }
        $data = session(self::SESSION_KEY, []);
        if (empty($data['first_name']) || empty($data['plan_id'])) {
            return redirect()->route('memorial.create.step1');
        }

        // Re-scoped rather than trusted from session: this is the path that grants a free
        // plan's entitlements with no payment step in between.
        $plan = SubscriptionPlan::sellableOnHost(\App\Helpers\ThemeSetting::siteTenantId(), $request->user())->find($data['plan_id']);
        $isFreePlan = $plan && $plan->isFree();

        $memorial = $this->createMemorialFromSession($request->user(), $data, $plan);
        session()->forget(self::SESSION_KEY);

        if ($isFreePlan && $plan) {
            $subscription = UserSubscription::create([
                'user_id' => $request->user()->id,
                'memorial_id' => $memorial->id,
                'subscription_plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => null,
                'status' => 'active',
                'payment_gateway' => null,
                'payment_reference' => null,
            ]);
            $memorial->update([
                'plan' => 'free',
                'subscription_plan_id' => $plan->id,
                'user_subscription_id' => $subscription->id,
            ]);

            return redirect()->route('memorial.create.preparing', ['slug' => $memorial->slug]);
        }

        return redirect()->route('subscription.index', [
            'from_signup' => 1,
            'plan_id' => $plan->id,
            'memorial_slug' => $memorial->slug,
        ])->with('info', 'Your memorial has been created. Complete your subscription to unlock premium features.');
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
            'shareMeta' => SiteShareMetaHelper::forNamedRoute(
                'Preparing your memorial',
                'memorial.create.preparing',
                ['slug' => $slug],
                'We’re finalizing the memorial page. You’ll be redirected shortly.'
            ),
        ]);
    }

    private function createMemorialFromSession($user, array $data, ?SubscriptionPlan $plan = null): Memorial
    {
        $fullName = trim(implode(' ', array_filter([
            $data['first_name'],
            $data['middle_name'] ?? '',
            $data['last_name'],
        ])));

        $planLabel = ($plan && ! $plan->isFree()) ? 'paid' : 'free';

        $memorial = Memorial::create([
            'user_id' => $user->id,
            'slug' => $this->generateSlug($fullName),
            'title' => 'In Loving Memory of '.$fullName,
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
            'biography' => null,
            'theme' => $planLabel,
            'plan' => $planLabel,
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

        return $memorial;
    }

    private function generateSlug(string $fullName): string
    {
        $baseSlug = Str::slug($fullName);
        $slug = $baseSlug;
        $suffix = 0;
        while (Memorial::where('slug', $slug)->exists()) {
            $suffix++;
            $slug = $baseSlug.'-'.$suffix;
        }

        return $slug;
    }
}
