<?php

namespace App\Http\Controllers\Reseller;

use App\Helpers\BrandingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    public function edit(Request $request)
    {
        $reseller = $request->user()->reseller;

        return view('pages.reseller.branding', [
            'title' => 'Branding',
            'reseller' => $reseller,
        ]);
    }

    public function update(Request $request)
    {
        $reseller = $request->user()->reseller;

        // Explicit mime allow-list rather than the `image` rule, which permits SVG. An SVG
        // can carry script, is stored on the public disk and served same-origin, and is
        // rendered as branding on this reseller's pages *and* their clients' memorials —
        // so accepting one is stored XSS against the families they serve.
        $request->validate([
            'primary_color' => 'nullable|string|max:20',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'favicon' => 'nullable|image|mimes:jpg,jpeg,png,webp,ico|max:512',
        ]);

        if ($primary = $request->input('primary_color')) {
            $reseller->primary_color = BrandingHelper::sanitizeHex($primary, $reseller->primary_color ?? '#465fff');
        }

        if ($request->hasFile('logo')) {
            if ($reseller->logo_path && Storage::disk('public')->exists($reseller->logo_path)) {
                Storage::disk('public')->delete($reseller->logo_path);
            }
            $reseller->logo_path = $request->file('logo')->store('reseller-branding', 'public');
        }

        if ($request->hasFile('favicon')) {
            if ($reseller->favicon_path && Storage::disk('public')->exists($reseller->favicon_path)) {
                Storage::disk('public')->delete($reseller->favicon_path);
            }
            $reseller->favicon_path = $request->file('favicon')->store('reseller-branding', 'public');
        }

        $reseller->save();

        return back()->with('success', 'Branding updated.');
    }
}
