<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResellerTier;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

/**
 * Full control over what resellers can be sold and what they're entitled to: pricing,
 * included memorial quota, overage price, and which platform capabilities (embedding,
 * domain routing, business analytics) each tier unlocks.
 */
class ResellerTierController extends Controller
{
    private const RULES = [
        'name' => ['required', 'string', 'max:255'],
        'annual_price' => ['required', 'numeric', 'min:0'],
        'memorial_profile_allowance' => ['nullable', 'integer', 'min:0'],
        'price_per_additional_profile' => ['required', 'numeric', 'min:0'],
        'storage_limit_gb' => ['nullable', 'integer', 'min:0'],
        'feature_embedding' => ['boolean'],
        'feature_domain_routing' => ['boolean'],
        'feature_business_analytics' => ['boolean'],
        'is_active' => ['boolean'],
        'sort_order' => ['integer', 'min:0'],
    ];

    public function index()
    {
        return view('pages.admin.reseller-pricing', [
            'title' => 'Reseller Pricing',
            'tiers' => ResellerTier::withCount('resellers')->orderBy('sort_order')->get(),
            'defaultTierId' => (int) SystemSetting::get('reseller.default_tier_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(array_merge(self::RULES, [
            'slug' => ['required', 'string', 'max:63', 'alpha_dash', 'unique:reseller_tiers,slug'],
        ]));

        ResellerTier::create(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
            'feature_embedding' => $request->boolean('feature_embedding'),
            'feature_domain_routing' => $request->boolean('feature_domain_routing'),
            'feature_business_analytics' => $request->boolean('feature_business_analytics'),
        ]));

        return back()->with('success', 'Tier created.');
    }

    public function update(Request $request, ResellerTier $resellerTier)
    {
        $validated = $request->validate(self::RULES);

        $resellerTier->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active'),
            'feature_embedding' => $request->boolean('feature_embedding'),
            'feature_domain_routing' => $request->boolean('feature_domain_routing'),
            'feature_business_analytics' => $request->boolean('feature_business_analytics'),
        ]));

        return back()->with('success', 'Tier updated.');
    }

    public function destroy(ResellerTier $resellerTier)
    {
        if ($resellerTier->resellers()->exists()) {
            return back()->with('error', 'Cannot delete a tier with resellers assigned to it — reassign them first.');
        }

        $resellerTier->delete();

        return back()->with('success', 'Tier deleted.');
    }
}
