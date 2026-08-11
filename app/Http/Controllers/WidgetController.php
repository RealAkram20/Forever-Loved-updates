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

    /**
     * The embeddable directory: the Find a Memorial experience minus the site chrome,
     * for an iframe on somebody else's website. Two modes — `memorials=all` renders the
     * whole catalogue of whichever site served the script (with search and pagination),
     * a comma-separated slug list renders just that curated set. Tenant scoping falls
     * out of the serving origin exactly as it does for the full directory page.
     */
    public function directory(\Illuminate\Http\Request $request)
    {
        $tenant = $this->embeddingTenantOrAbort();

        $selection = trim((string) $request->query('memorials', 'all'));
        $slugs = ($selection === '' || strtolower($selection) === 'all')
            ? []
            : array_slice(array_values(array_filter(array_map('trim', explode(',', $selection)))), 0, 60);

        return view('pages.widget.directory', [
            'mode' => $slugs === [] ? 'all' : 'picked',
            'slugs' => $slugs,
            'tenant' => $tenant,
        ]);
    }

    /** JSON for the directory widget — the directory page's own results, gate included. */
    public function directoryResults(\Illuminate\Http\Request $request)
    {
        $this->embeddingTenantOrAbort();

        return app(MemorialDirectoryController::class)->directoryResults($request);
    }

    /**
     * Same rule the single widget applies memorial-by-memorial, asked of the serving
     * site: a reseller's origin only embeds if their tier includes embedding.
     */
    private function embeddingTenantOrAbort(): ?Reseller
    {
        $tenant = \App\Helpers\ThemeSetting::siteTenant();

        if ($tenant && ! $tenant->tier?->feature_embedding) {
            abort(403, 'Embedding is not included in this reseller\'s current plan.');
        }

        return $tenant;
    }
}
