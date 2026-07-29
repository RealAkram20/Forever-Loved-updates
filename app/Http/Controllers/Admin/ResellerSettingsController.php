<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerTier;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

/**
 * Program-wide reseller configuration — the knobs that apply to every reseller rather
 * than to one of them. Kept out of Admin\SettingsController, which is already carrying
 * a dozen unrelated setting groups.
 */
class ResellerSettingsController extends Controller
{
    public function edit()
    {
        return view('pages.admin.reseller-settings', [
            'title' => 'Reseller Settings',
            'settings' => array_merge(
                SystemSetting::getByGroup('domains'),
                SystemSetting::getByGroup('reseller'),
            ),
            'tiers' => ResellerTier::orderBy('sort_order')->get(),
            // Shown read-only so an admin can see what's already fenced off before adding.
            'baseReservedSlugs' => Reseller::baseReservedSlugs(),
            'resellerCount' => Reseller::count(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'custom_domains_enabled' => ['required', 'in:0,1'],
            'target_host' => ['nullable', 'string', 'max:255'],
            'default_tier_id' => ['nullable', 'exists:reseller_tiers,id'],
            'reserved_slugs' => ['nullable', 'string', 'max:2000'],
        ]);

        SystemSetting::set('domains.custom_domains_enabled', $request->boolean('custom_domains_enabled') ? '1' : '0');
        SystemSetting::set('domains.target_host', trim((string) $request->input('target_host', '')));
        SystemSetting::set('reseller.default_tier_id', (string) $request->input('default_tier_id', ''));

        // Store normalized (lowercase, comma-separated) so the value round-trips into the
        // textarea looking the same way it will actually be matched.
        $slugs = preg_split('/[\s,]+/', strtolower((string) $request->input('reserved_slugs', '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        SystemSetting::set('reseller.reserved_slugs', implode(', ', array_unique($slugs)));

        return back()->with('success', 'Reseller settings updated.');
    }
}
