<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Services\PesapalService;
use Illuminate\Http\Request;

/**
 * Lets reseller staff self-serve wire up their own Pesapal merchant account, so their
 * clients' payments land directly with them instead of the platform. No platform
 * approval gate in Phase 1 — confirmed with the business owner as an automatic,
 * self-serve capability for any active reseller.
 */
class PaymentSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $reseller = $request->user()->reseller;

        return view('pages.reseller.payments', [
            'title' => 'Payment Settings',
            'reseller' => $reseller,
        ]);
    }

    public function update(Request $request)
    {
        $reseller = $request->user()->reseller;

        $validated = $request->validate([
            'pesapal_enabled' => 'boolean',
            'pesapal_environment' => 'required|in:sandbox,live',
            'pesapal_consumer_key' => 'nullable|string|max:255',
            'pesapal_consumer_secret' => 'nullable|string|max:255',
        ]);

        $reseller->pesapal_environment = $validated['pesapal_environment'];
        $enabling = $request->boolean('pesapal_enabled');

        if (! empty($validated['pesapal_consumer_key'])) {
            $reseller->pesapal_consumer_key = $validated['pesapal_consumer_key'];
        }
        if (! empty($validated['pesapal_consumer_secret']) && $validated['pesapal_consumer_secret'] !== '••••••••') {
            $reseller->pesapal_consumer_secret = $validated['pesapal_consumer_secret'];
        }

        // An IPN id belongs to one merchant account in one environment. Keeping it
        // across a key or sandbox→live change makes every checkout send a
        // notification_id the new account does not own, and Pesapal rejects the order —
        // the classic "worked in sandbox, dead in live" failure. Dropping it is safe:
        // the service re-registers one on the next submit.
        if ($reseller->isDirty(['pesapal_consumer_key', 'pesapal_consumer_secret', 'pesapal_environment'])) {
            $reseller->pesapal_ipn_id = null;
        }

        // Turning this on without credentials is worse than leaving it off: the service
        // silently falls back to the platform's account, so the reseller believes they are
        // collecting their clients' payments while the money lands with us.
        if ($enabling && (blank($reseller->pesapal_consumer_key) || blank($reseller->pesapal_consumer_secret))) {
            return back()->withInput()->withErrors([
                'pesapal_consumer_key' => 'Add both your consumer key and secret before enabling Pesapal — until then, payments would go to the platform rather than to you.',
            ]);
        }

        // Clearing is only possible while switching off, so an enabled account can never
        // be left credential-less by a blank submission.
        if (! $enabling && $request->boolean('clear_credentials')) {
            $reseller->pesapal_consumer_key = null;
            $reseller->pesapal_consumer_secret = null;
            $reseller->pesapal_ipn_id = null;
        }

        $reseller->pesapal_enabled = $enabling;
        $reseller->save();

        return back()->with('success', 'Payment settings updated.');
    }

    /**
     * Registers this reseller's own IPN listener with Pesapal, mirroring the admin
     * "Generate IPN" action but scoped to the reseller's own credentials.
     */
    public function registerIpn(Request $request)
    {
        $reseller = $request->user()->reseller;

        // Without their own credentials, PesapalService::forReseller() hands back a
        // platform-scoped service whose registerIpn() writes the *global*
        // payments.pesapal_ipn_id setting — a reseller could break the platform's own
        // checkout from their settings page, and get a false success message doing it.
        if (! $reseller->hasOwnPesapalCredentials()) {
            return back()->with('error', 'Add and enable your own Pesapal consumer key and secret before registering an IPN.');
        }

        $result = PesapalService::forReseller($reseller)->registerIpn();

        if (! $result['success']) {
            return back()->with('error', $result['error'] ?? 'Could not register IPN with Pesapal.');
        }

        return back()->with('success', 'IPN registered successfully.');
    }
}
