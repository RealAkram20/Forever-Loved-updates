<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ResellerController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status');

        $resellers = Reseller::with('tier', 'owner')
            ->withCount(['memorials', 'staff', 'plans'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->input('q').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('custom_domain', 'like', $term));
            })
            ->when(in_array($status, [Reseller::STATUS_ACTIVE, Reseller::STATUS_SUSPENDED], true),
                fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('pages.admin.resellers', [
            'title' => 'Resellers',
            'resellers' => $resellers,
            'tiers' => ResellerTier::orderBy('sort_order')->get(),
            'stats' => [
                'total' => Reseller::count(),
                'active' => Reseller::where('status', Reseller::STATUS_ACTIVE)->count(),
                'suspended' => Reseller::where('status', Reseller::STATUS_SUSPENDED)->count(),
                'memorials' => Memorial::whereNotNull('reseller_id')->count(),
            ],
            'defaultTierId' => SystemSetting::get('reseller.default_tier_id') ?: null,
        ]);
    }

    /**
     * Per-reseller drill-down. The roster can only ever show counts; this is where an
     * admin answers "which memorials/staff/plans" — otherwise the only route to that
     * answer is scanning the unfiltered platform-wide lists.
     */
    public function show(Reseller $reseller)
    {
        $reseller->load('tier', 'owner')->loadCount(['memorials', 'staff', 'plans']);

        return view('pages.admin.reseller-show', [
            'title' => $reseller->name,
            'reseller' => $reseller,
            'tiers' => ResellerTier::orderBy('sort_order')->get(),
            'memorials' => $reseller->memorials()->with('owner')->latest()->limit(10)->get(),
            'staff' => $reseller->staff()->orderBy('name')->get(),
            'plans' => $reseller->plans()->orderBy('price')->get(),
            'orders' => $reseller->paymentOrders()->with('user')->latest()->limit(10)->get(),
            'revenue' => $reseller->paymentOrders()->where('status', 'completed')->sum('amount'),
            'rolledOverCount' => User::where('original_reseller_id', $reseller->id)->whereNull('reseller_id')->count(),
        ]);
    }

    public function store(Request $request)
    {
        // Normalize before validating so a submitted "WWW" is checked (and later
        // stored) as "www" — otherwise it slips past the reserved-word rule here
        // and collides with it once lowercased on save.
        $request->merge(['slug' => strtolower((string) $request->input('slug'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:63', 'alpha_dash', 'unique:resellers,slug', Rule::notIn(Reseller::reservedSlugs())],
            'reseller_tier_id' => ['nullable', 'exists:reseller_tiers,id'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $reseller = Reseller::create([
            'name' => $validated['name'],
            'slug' => strtolower($validated['slug']),
            // Falling back to the program's default tier means the common case (everyone
            // starts on the same tier) needs no thought at creation time.
            'reseller_tier_id' => $validated['reseller_tier_id'] ?: (SystemSetting::get('reseller.default_tier_id') ?: null),
            'status' => Reseller::STATUS_ACTIVE,
        ]);

        $owner = User::create([
            'name' => $validated['owner_name'],
            'email' => $validated['owner_email'],
            'password' => null,
            'reseller_id' => $reseller->id,
            'original_reseller_id' => $reseller->id,
        ]);
        $owner->assignRole('reseller');

        $reseller->update(['owner_user_id' => $owner->id]);

        return back()->with('success', "Reseller \"{$reseller->name}\" created.");
    }

    public function update(Request $request, Reseller $reseller)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'reseller_tier_id' => ['nullable', 'exists:reseller_tiers,id'],
        ]);

        $reseller->update($validated);

        return back()->with('success', 'Reseller updated.');
    }

    public function suspend(Reseller $reseller)
    {
        $reseller->update(['status' => Reseller::STATUS_SUSPENDED]);

        return back()->with('success', "\"{$reseller->name}\" suspended. Their existing memorials and subdomain keep working — only their own dashboard access is blocked.");
    }

    public function activate(Reseller $reseller)
    {
        $reseller->update(['status' => Reseller::STATUS_ACTIVE]);

        return back()->with('success', "\"{$reseller->name}\" reactivated.");
    }

    /**
     * Support override for a reseller's custom domain: lets an admin mark it verified
     * when DNS was confirmed fine out-of-band (e.g. propagation lag hiding a TXT record
     * that's actually already correct), without waiting on the reseller to re-check it.
     */
    public function verifyDomain(Reseller $reseller)
    {
        if (! $reseller->custom_domain) {
            return back()->with('error', 'This reseller has not set a custom domain yet.');
        }

        $reseller->update([
            'custom_domain_status' => Reseller::DOMAIN_VERIFIED,
            'custom_domain_verified_at' => now(),
        ]);

        return back()->with('success', "\"{$reseller->custom_domain}\" marked as verified.");
    }

    /**
     * Partnership has ended: reassign every client/memorial back to direct platform
     * ownership. original_reseller_id is left untouched so restore() can reverse this
     * if the reseller renews later.
     */
    public function rollover(Reseller $reseller)
    {
        User::where('reseller_id', $reseller->id)->update(['reseller_id' => null]);
        Memorial::where('reseller_id', $reseller->id)->update(['reseller_id' => null]);

        return back()->with('success', "\"{$reseller->name}\"'s clients and memorials rolled over to direct platform ownership.");
    }

    /** Reverses a prior rollover when a reseller renews. */
    public function restore(Reseller $reseller)
    {
        User::where('original_reseller_id', $reseller->id)->whereNull('reseller_id')->update(['reseller_id' => $reseller->id]);
        Memorial::where('original_reseller_id', $reseller->id)->whereNull('reseller_id')->update(['reseller_id' => $reseller->id]);

        return back()->with('success', "\"{$reseller->name}\"'s clients and memorials reassigned back to them.");
    }

    /**
     * Super-admins can always access a reseller's dashboard for support, without
     * needing a separate reseller-role account of their own.
     */
    public function loginAs(Request $request, Reseller $reseller)
    {
        if (! $reseller->owner) {
            return back()->with('error', 'This reseller has no owner account to log in as yet.');
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($reseller->owner);

        return redirect()->route('dashboard');
    }

    public function stopImpersonating(Request $request)
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        $admin = $impersonatorId ? User::find($impersonatorId) : null;

        if (! $admin) {
            return redirect()->route('dashboard');
        }

        Auth::login($admin);

        return redirect()->route('settings.resellers');
    }
}
