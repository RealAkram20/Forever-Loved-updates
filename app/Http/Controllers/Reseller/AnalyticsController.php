<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\MemorialView;
use App\Models\Tribute;
use Illuminate\Http\Request;

/**
 * Business analytics for a reseller: how much attention the memorials they host are
 * actually getting.
 *
 * The reseller_tiers table has carried a feature_business_analytics flag since the tiers
 * were built, and it gated nothing — there was no analytics feature for it to unlock. An
 * admin could sell a tier on it and the reseller would find no such page.
 */
class AnalyticsController extends Controller
{
    private const WINDOW_DAYS = 30;

    public function index(Request $request)
    {
        $reseller = $request->user()->reseller()->with('tier')->first();

        // Not in their tier: show what the feature is rather than a 403. It is a paid
        // capability, not forbidden data — all of it is their own.
        if (! $reseller->tierAllows('business_analytics')) {
            return view('pages.reseller.analytics', [
                'title' => 'Analytics',
                'reseller' => $reseller,
                'locked' => true,
            ]);
        }

        $memorialIds = $reseller->memorials()->select('id');
        $since = now()->subDays(self::WINDOW_DAYS - 1)->startOfDay();

        // Grouped in SQL, not in PHP: this walks every view row for the tenant, and a
        // popular memorial accumulates them fast.
        $daily = MemorialView::whereIn('memorial_id', $memorialIds)
            ->where('viewed_at', '>=', $since)
            ->selectRaw('DATE(viewed_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        // Every day in the window, including the empty ones — gaps in a time series must
        // read as zero, not as an absent bar that compresses the timeline.
        $series = collect(range(self::WINDOW_DAYS - 1, 0))->map(function ($daysAgo) use ($daily) {
            $date = now()->subDays($daysAgo)->startOfDay();

            return [
                'date' => $date,
                'label' => $date->format('M j'),
                'views' => (int) ($daily[$date->toDateString()] ?? 0),
            ];
        });

        return view('pages.reseller.analytics', [
            'title' => 'Analytics',
            'reseller' => $reseller,
            'locked' => false,
            'windowDays' => self::WINDOW_DAYS,
            'series' => $series,
            'viewsInWindow' => $series->sum('views'),
            'viewsAllTime' => MemorialView::whereIn('memorial_id', $memorialIds)->count(),
            'visitorsInWindow' => MemorialView::whereIn('memorial_id', $memorialIds)
                ->where('viewed_at', '>=', $since)
                ->distinct('visitor_hash')
                ->count('visitor_hash'),
            'tributesAllTime' => Tribute::whereIn('memorial_id', $memorialIds)->count(),
            'topMemorials' => $reseller->memorials()
                ->withCount(['views', 'tributes'])
                ->orderByDesc('views_count')
                ->limit(10)
                ->get(),
        ]);
    }
}
