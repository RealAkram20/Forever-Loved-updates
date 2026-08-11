<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\SystemSetting;
use App\Services\DomainVerificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The reseller's own account-level settings — business name and custom domain.
 * Slug/subdomain and tier stay admin-controlled (Admin\ResellerController) since
 * changing either has platform-wide consequences (routing, entitlements). Branding
 * (logo/favicon/color) and payment gateway credentials live on their own pages —
 * this is the landing hub that links out to them.
 */
class SettingsController extends Controller
{
    public function edit(Request $request)
    {
        $reseller = $request->user()->reseller()->with('tier')->first();

        return view('pages.reseller.settings', [
            'title' => 'Settings',
            'reseller' => $reseller,
            // Two independent gates: the platform must offer custom domains at all, and
            // this reseller's tier must include domain routing. Both have to hold.
            'domainsEnabled' => SystemSetting::get('domains.custom_domains_enabled', false),
            'domainRoutingInTier' => $reseller->tierAllows('domain_routing'),
            'domainTargetHost' => SystemSetting::get('domains.target_host', ''),
            'domainTargetIp' => SystemSetting::get('domains.target_ip', ''),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Nullable: blank falls back to the platform's address rather than dropping the
            // enquiry, so an unset field is a routing choice and not a silent hole.
            'contact_email' => ['nullable', 'email', 'max:255'],
        ]);

        $request->user()->reseller->update($validated);

        return back()->with('success', 'Settings updated.');
    }

    public function updateCustomDomain(Request $request, DomainVerificationService $domains)
    {
        $reseller = $request->user()->reseller()->with('tier')->first();

        abort_unless(
            SystemSetting::get('domains.custom_domains_enabled', false) && $reseller->tierAllows('domain_routing'),
            403,
            'Custom domains are not included in your current tier.'
        );

        $validated = $request->validate([
            'custom_domain' => [
                'required', 'string', 'max:255',
                'regex:/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/i',
                Rule::unique('resellers', 'custom_domain')->ignore($reseller->id),
            ],
        ]);

        $domain = strtolower($validated['custom_domain']);

        // Re-saving the same domain must change nothing. Minting a fresh token here
        // invalidated the TXT record already sitting in their DNS, reset a verified
        // domain to unverified — which un-routes a live site — and restarted the DNS
        // cache clock, all from pressing Update without editing anything.
        if ($domain === $reseller->custom_domain) {
            return back()->with('success', 'That is already your domain — nothing changed.');
        }

        $reseller->update([
            'custom_domain' => $domain,
            'custom_domain_token' => $domains->generateToken(),
            'custom_domain_status' => Reseller::DOMAIN_UNVERIFIED,
            'custom_domain_verified_at' => null,
        ]);

        return back()->with('success', 'Custom domain saved. Add the TXT record below, then verify it.');
    }

    public function verifyCustomDomain(Request $request, DomainVerificationService $domains)
    {
        $reseller = $request->user()->reseller()->with('tier')->first();

        abort_unless(
            SystemSetting::get('domains.custom_domains_enabled', false) && $reseller->tierAllows('domain_routing'),
            403,
            'Custom domains are not included in your current tier.'
        );

        if (! $reseller->custom_domain) {
            return back()->with('error', 'Add a custom domain first.');
        }

        $verified = $domains->verifyTxt($reseller->custom_domain, $reseller->custom_domain_token);

        // A failed lookup must never demote a domain that already proved itself: DNS
        // caches lie for as long as their TTL, and demoting un-routes a live site —
        // pressing Verify twice took a family's memorial pages offline.
        if (! $verified && $reseller->hasVerifiedCustomDomain()) {
            return back()->with('success', 'Already verified — nothing to redo. The TXT record can even be removed now.');
        }

        $reseller->update([
            'custom_domain_status' => $verified ? Reseller::DOMAIN_VERIFIED : Reseller::DOMAIN_FAILED,
            'custom_domain_verified_at' => $verified ? now() : null,
        ]);

        if ($verified) {
            // Routed the moment the check passes — "verified" should mean live, not
            // live within a minute.
            app(\App\Services\CustomDomainProxySync::class)->sync();
        }

        return back()->with(
            $verified ? 'success' : 'error',
            $verified
                ? 'Domain verified! Add the records shown below to go live.'
                : "Couldn't find the TXT record yet. DNS changes can take a while to propagate — try again shortly."
        );
    }
}
