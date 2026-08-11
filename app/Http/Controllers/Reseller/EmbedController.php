<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Memorial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The embed snippet builder: search-and-pick memorials (or choose all), get the
 * copy-paste script for any external website. The picking happens here in the
 * dashboard; the rendering happens wherever the snippet is pasted, served from the
 * reseller's own origin so it wears their branding and lists only their memorials.
 */
class EmbedController extends Controller
{
    public function show(Request $request)
    {
        $reseller = $request->user()->reseller()->with('tier')->first();

        return view('pages.reseller.embed', [
            'title' => 'Embed on your website',
            'reseller' => $reseller,
            'embeddingInTier' => (bool) $reseller->tier?->feature_embedding,
            // The script must load from THEIR site: that origin is what scopes and
            // brands everything the widget shows.
            'embedOrigin' => rtrim($reseller->publicBaseUrl(), '/'),
        ]);
    }

    /**
     * Dropdown results for the picker. Keyed to the signed-in user's reseller — not
     * the serving host — so the builder works identically on their domain and ours.
     */
    public function search(Request $request): JsonResponse
    {
        $reseller = $request->user()->reseller;
        $q = trim((string) $request->query('q', ''));

        $memorials = Memorial::query()
            ->where('reseller_id', $reseller->id)
            ->where('is_public', true)
            ->where('status', Memorial::STATUS_ACTIVE)
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%")))
            ->orderBy('full_name')
            ->limit(12)
            ->get(['id', 'slug', 'full_name', 'profile_photo_path', 'date_of_birth', 'date_of_passing', 'birth_year', 'death_year']);

        return response()->json([
            'data' => $memorials->map(fn (Memorial $m) => [
                'slug' => $m->slug,
                'name' => $m->full_name,
                'years' => $m->birth_death_years,
                'photo' => \App\Helpers\ResponsiveImage::url($m->profile_photo_path, 160),
            ]),
        ]);
    }
}
