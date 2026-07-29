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
        $reseller->pesapal_enabled = $request->boolean('pesapal_enabled');

        if (! empty($validated['pesapal_consumer_key'])) {
            $reseller->pesapal_consumer_key = $validated['pesapal_consumer_key'];
        }
        if (! empty($validated['pesapal_consumer_secret']) && $validated['pesapal_consumer_secret'] !== '••••••••') {
            $reseller->pesapal_consumer_secret = $validated['pesapal_consumer_secret'];
        }

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

        $result = PesapalService::forReseller($reseller)->registerIpn();

        if (! $result['success']) {
            return back()->with('error', $result['error'] ?? 'Could not register IPN with Pesapal.');
        }

        return back()->with('success', 'IPN registered successfully.');
    }
}
