<?php

namespace App\Services\Reports;

use App\Models\Memorial;
use App\Models\MemorialShare;
use App\Models\MemorialView;
use App\Models\Tribute;
use App\Reports\ReportBranding;
use App\Reports\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Assembles the one-page report a reseller hands to a family.
 *
 * Not a Report in the registry sense: that contract describes a table, and this is a
 * document — a portrait page with a photo, a chart, and reprinted messages. Forcing it
 * into columns would have produced neither a good table nor a good keepsake.
 *
 * One privacy rule runs through all of it: visitors are only ever counted. memorial_views
 * stores a hash and a timestamp, deliberately — no names, no addresses, no locations. The
 * report says "247 people visited" and must never imply the family can learn who they were.
 */
class MemorialReportBuilder
{
    public function build(Memorial $memorial, ReportFilters $filters, bool $includeMessages = true): array
    {
        $views = $filters->applyTo(MemorialView::where('memorial_id', $memorial->id), 'viewed_at');
        $tributes = $filters->applyTo(Tribute::where('memorial_id', $memorial->id), 'created_at');
        $shares = $filters->applyTo(MemorialShare::where('memorial_id', $memorial->id), 'shared_at');

        $series = $this->dailySeries($memorial, $filters);

        return [
            'memorial' => $memorial,
            'filters' => $filters,
            'branding' => $memorial->reseller
                ? ReportBranding::forReseller($memorial->reseller)
                : ReportBranding::platform(),
            // Resolved to a filesystem path here, not left as a URL: dompdf reads images
            // off disk and remote fetching stays disabled.
            'photoPath' => $memorial->profile_photo_path
                ? ReportBranding::localPath(asset('storage/'.$memorial->profile_photo_path))
                : null,

            'visits' => (clone $views)->count(),
            'visitors' => (clone $views)->distinct('visitor_hash')->count('visitor_hash'),
            'firstVisit' => (clone $views)->min('viewed_at'),
            'lastVisit' => (clone $views)->max('viewed_at'),

            'series' => $series,
            'busiestDay' => $series->sortByDesc('visits')->first(),

            'tributeCount' => (clone $tributes)->where('is_approved', true)->count(),
            'tributesByType' => (clone $tributes)->where('is_approved', true)
                ->selectRaw('type, COUNT(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type'),

            // Only approved messages are ever reprinted. A tribute awaiting moderation has
            // not been seen by anyone responsible for the memorial, and a printed document
            // is the worst possible place for it to surface first.
            'messages' => $includeMessages
                ? (clone $tributes)->where('is_approved', true)
                    ->whereNotNull('message')
                    ->where('message', '!=', '')
                    ->with('user:id,name')
                    ->latest()
                    ->limit(60)
                    ->get()
                : collect(),
            'messagesIncluded' => $includeMessages,

            'shareCount' => (clone $shares)->count(),
            'sharesByChannel' => (clone $shares)
                ->selectRaw('share_type, COUNT(*) as total')
                ->groupBy('share_type')
                ->pluck('total', 'share_type'),

            'chapterCount' => $memorial->storyChapters()->count(),
            'photoCount' => $memorial->media()->where('type', 'image')->count(),
            'videoCount' => $memorial->media()->where('type', 'video')->count(),
            'collaborators' => $memorial->collaborators()
                ->whereNotNull('accepted_at')
                ->with('user:id,name')
                ->get(),
        ];
    }

    /**
     * A row per day across the window, including the empty ones — a gap in a time series
     * has to read as zero, not as absent time that compresses the chart.
     */
    private function dailySeries(Memorial $memorial, ReportFilters $filters): Collection
    {
        $from = $filters->from ?? $this->firstActivity($memorial);
        $to = $filters->to ?? CarbonImmutable::now();

        // A memorial with no activity at all has no series to draw.
        if (! $from) {
            return collect();
        }

        $daily = MemorialView::where('memorial_id', $memorial->id)
            ->whereBetween('viewed_at', [$from, $to])
            ->selectRaw('DATE(viewed_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        // Long windows are bucketed rather than drawn as hundreds of hairlines; a year of
        // daily bars on a printed page is texture, not information.
        $days = (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
        $days = min($days, 366);

        return collect(range(0, $days - 1))->map(function (int $offset) use ($from, $daily) {
            $date = $from->startOfDay()->addDays($offset);

            return [
                'date' => $date,
                'label' => $date->format('j M'),
                'visits' => (int) ($daily[$date->toDateString()] ?? 0),
            ];
        });
    }

    private function firstActivity(Memorial $memorial): ?CarbonImmutable
    {
        $first = MemorialView::where('memorial_id', $memorial->id)->min('viewed_at');

        return $first ? CarbonImmutable::parse($first) : null;
    }
}
