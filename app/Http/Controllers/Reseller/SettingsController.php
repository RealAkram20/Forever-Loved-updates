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

        $contact = [];
        foreach (\App\Support\SiteContactDetails::keys() as $key) {
            $contact[$key] = \App\Models\ResellerSetting::allFor($reseller->id)[$key]['value'] ?? '';
        }

        return view('pages.reseller.settings', [
            'title' => 'Settings',
            'reseller' => $reseller,
            'contact' => $contact,
            // The line under the logo in the footer. Read from this reseller's own row rather
            // than the platform's branding.tagline, so the field shows what they wrote and
            // stays empty — showing its placeholder — when they have written nothing.
            'tagline' => \App\Models\ResellerSetting::allFor($reseller->id)['branding.tagline']['value'] ?? '',
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
            'tagline' => ['nullable', 'string', 'max:255'],
        ]);

        $reseller = $request->user()->reseller;

        $reseller->update(['name' => $validated['name'], 'contact_email' => $validated['contact_email'] ?? null]);

        // Kept as this reseller's own row rather than written to the platform's
        // branding.tagline, which is ours and is what every other site falls back to. Blank
        // clears the row, so their footer shows nothing rather than inheriting our line on
        // their business's page.
        $tagline = trim((string) ($validated['tagline'] ?? ''));

        if ($tagline === '') {
            \App\Models\ResellerSetting::forget($reseller->id, 'branding.tagline');
        } else {
            \App\Models\ResellerSetting::set($reseller->id, 'branding.tagline', $tagline);
        }

        return back()->with('success', 'Settings updated.');
    }

    /**
     * Address, phones, hours, socials and the map.
     *
     * Facts about the business rather than appearance, so they are written here and not from
     * the theme — a reseller who switches theme keeps their address. Blank clears the value,
     * which is a real choice: a row nobody filled in is not rendered at all.
     */
    public function updateContact(Request $request)
    {
        $reseller = $request->user()->reseller;

        $rules = [];
        foreach (\App\Support\SiteContactDetails::rules() as $key => $rule) {
            $rules['contact.'.$key] = $rule;
        }

        $request->validate($rules);

        $submitted = (array) $request->input('contact', []);

        // The map value is rendered inside an iframe, so it is checked against the host
        // allow-list here rather than trusted because it came from a signed-in reseller.
        $map = trim((string) ($submitted[\App\Support\SiteContactDetails::MAP_EMBED] ?? ''));
        if ($map !== '') {
            $extracted = $map;
            if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $map, $m)) {
                $extracted = html_entity_decode($m[1], ENT_QUOTES);
            }

            if (! \App\Support\SiteContactDetails::isAllowedMapUrl($extracted)) {
                return back()
                    ->withInput()
                    ->withErrors(['contact.'.\App\Support\SiteContactDetails::MAP_EMBED =>
                        'That does not look like a '.\App\Support\SiteContactDetails::allowedMapHostsLabel().' embed. Paste the whole <iframe> snippet from "Embed a map", or just its src address.']);
            }
        }

        foreach (\App\Support\SiteContactDetails::keys() as $key) {
            $value = trim((string) ($submitted[$key] ?? ''));

            if ($value === '') {
                \App\Models\ResellerSetting::forget($reseller->id, $key);

                continue;
            }

            \App\Models\ResellerSetting::set($reseller->id, $key, $value);
        }

        \App\Helpers\ThemeSetting::forgetThemeTokens($reseller->id);

        return back()->with('success', 'Contact details updated.');
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
