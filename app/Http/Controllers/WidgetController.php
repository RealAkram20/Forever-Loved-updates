<?php

namespace App\Http\Controllers;

use App\Models\Memorial;
use App\Models\Reseller;

/**
 * Read-only, chrome-less memorial view for embedding on a reseller's own site via
 * public/embed.js. No in-iframe write actions (leaving a tribute, etc.) — session
 * cookies are SameSite=Lax and won't ride along on a cross-origin iframe's requests,
 * so interactive actions link out of the iframe to the full memorial page instead.
 */
class WidgetController extends Controller
{
    public function show(string $slug)
    {
        $memorial = Memorial::where('slug', $slug)->firstOrFail();

        if (! $memorial->is_public) {
            abort(404);
        }

        if (in_array($memorial->status ?? 'active', ['deactivated', 'suspended'])) {
            abort(404);
        }

        if ($memorial->expires_at?->isPast()) {
            abort(404);
        }

        if ($memorial->reseller_id && ! $memorial->reseller?->tier?->feature_embedding) {
            abort(403, 'Embedding is not included in this reseller\'s current plan.');
        }

        // Bind the tenant so BrandingHelper/AppearanceHelper resolve *their* palette, logo and
        // fonts rather than the platform's. This route has no reseller middleware — nothing
        // bound it before, so the one feature explicitly sold as white-label embedding
        // rendered in the platform's colours on the reseller's own website.
        if ($memorial->reseller) {
            app()->instance(Reseller::class, $memorial->reseller);
        }

        $memorial->load('media', 'storyChapters');

        return view('pages.widget.show', [
            'memorial' => $memorial,
            'fullUrl' => $memorial->publicUrl(),
        ]);
    }
}
