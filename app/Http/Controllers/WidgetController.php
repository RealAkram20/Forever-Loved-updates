<?php

namespace App\Http\Controllers;

use App\Models\Memorial;

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

        $memorial->load('media', 'storyChapters');

        return view('pages.widget.show', [
            'memorial' => $memorial,
            'fullUrl' => $memorial->publicUrl(),
        ]);
    }
}
