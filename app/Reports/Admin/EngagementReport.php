<?php

namespace App\Reports\Admin;

use App\Models\Memorial;
use App\Models\MemorialShare;
use App\Models\MemorialView;
use App\Models\Tribute;
use App\Models\User;
use App\Reports\BaseReport;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Whether anyone is actually visiting.
 *
 * Everything here counts rows in memorial_views, never memorials.visitor_count. That
 * column is a denormalised counter kept for the public page and it drifts; two numbers
 * that disagree are worse than one that is slow. Where the drift is large enough to
 * notice, the summary says so rather than picking a side silently.
 */
class EngagementReport extends BaseReport
{
    /** Below this the counter and the table always look different; not worth reporting. */
    private const DRIFT_THRESHOLD = 50;

    public function key(): string
    {
        return 'engagement';
    }

    public function title(): string
    {
        return 'Engagement';
    }

    public function description(): string
    {
        return 'Visits, visitors, tributes and shares, and which memorials draw them.';
    }

    public function group(): string
    {
        return 'Content';
    }

    public function dateWindowNote(): ?string
    {
        return 'By the date each visit, tribute or share happened. Rows are the memorials visited in that window.';
    }

    public function availableTo(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin']);
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('memorial', 'Memorial', '22%'),
            ReportColumn::text('owner', 'Owner', '16%'),
            ReportColumn::number('views', 'Visits', '10%'),
            ReportColumn::number('visitors', 'Unique visitors', '12%'),
            ReportColumn::number('tributes', 'Tributes', '10%'),
            ReportColumn::number('shares', 'Shares', '9%'),
            ReportColumn::date('last_view', 'Last visit', '10%'),
            ReportColumn::text('reseller', 'Hosted by', '11%'),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $views = $filters->applyTo(MemorialView::query(), 'viewed_at')->count();
        $visitors = $filters->applyTo(MemorialView::query(), 'viewed_at')->distinct('visitor_hash')->count('visitor_hash');
        $tributes = $filters->applyTo(Tribute::query(), 'created_at')->count();
        $shares = $filters->applyTo(MemorialShare::query(), 'shared_at')->count();
        $awaiting = Tribute::where('is_approved', false)->count();

        $stats = [
            ReportStat::make('Visits', $this->number($views)),
            ReportStat::make('Unique visitors', $this->number($visitors), $views > 0 ? $this->ratio($visitors, $views).' of visits' : null),
            ReportStat::make('Tributes left', $this->number($tributes)),
            ReportStat::make('Shares', $this->number($shares)),
            ReportStat::make(
                'Awaiting moderation',
                $this->number($awaiting),
                'All time',
                $awaiting > 0 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
        ];

        if ($note = $this->driftNote()) {
            $stats[] = $note;
        }

        return $stats;
    }

    /**
     * The stored counter against the actual view records. Surfaced rather than hidden:
     * an admin comparing this report with a memorial page deserves to know which number
     * is which, instead of concluding one of them is broken.
     */
    private function driftNote(): ?ReportStat
    {
        $counterTotal = (int) Memorial::sum('visitor_count');
        $recordTotal = MemorialView::count();
        $drift = abs($counterTotal - $recordTotal);

        if ($drift < self::DRIFT_THRESHOLD) {
            return null;
        }

        return ReportStat::make(
            'Counter drift',
            $this->number($drift),
            'The memorials.visitor_count counter differs from the visit records. This report uses the records.',
            ReportStat::TONE_WARNING,
        );
    }

    public function total(ReportFilters $filters): int
    {
        return $this->memorialIdsInWindow($filters)->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        // One grouped pass per metric, then joined in PHP by memorial id. The alternative
        // — a correlated subquery per column — turns a report over a busy month into one
        // query per memorial.
        $views = $filters->applyTo(MemorialView::query(), 'viewed_at')
            ->selectRaw('memorial_id, COUNT(*) as views, COUNT(DISTINCT visitor_hash) as visitors, MAX(viewed_at) as last_view')
            ->groupBy('memorial_id')
            ->get()
            ->keyBy('memorial_id');

        $tributes = $filters->applyTo(Tribute::query(), 'created_at')
            ->selectRaw('memorial_id, COUNT(*) as total')
            ->groupBy('memorial_id')
            ->pluck('total', 'memorial_id');

        $shares = $filters->applyTo(MemorialShare::query(), 'shared_at')
            ->selectRaw('memorial_id, COUNT(*) as total')
            ->groupBy('memorial_id')
            ->pluck('total', 'memorial_id');

        $ids = $views->keys()
            ->merge($tributes->keys())
            ->merge($shares->keys())
            ->unique()
            ->values();

        // Materialised rather than streamed, uniquely among the reports: the ordering is
        // by a computed column, which cannot be resolved without holding every row. The
        // set is bounded by "memorials with activity in this window", not by the view
        // table, so it stays small even when the underlying counts are large.
        $rows = Memorial::query()
            ->whereIn('id', $ids)
            ->with(['owner:id,name', 'reseller:id,name'])
            ->get()
            ->map(fn (Memorial $memorial) => $this->shape([
                'memorial' => $memorial->full_name,
                'owner' => $memorial->owner?->name,
                'views' => (int) ($views[$memorial->id]->views ?? 0),
                'visitors' => (int) ($views[$memorial->id]->visitors ?? 0),
                'tributes' => (int) ($tributes[$memorial->id] ?? 0),
                'shares' => (int) ($shares[$memorial->id] ?? 0),
                'last_view' => $views[$memorial->id]->last_view ?? null,
                'reseller' => $memorial->reseller?->name ?? 'Platform',
            ]))
            ->sortByDesc('views')
            ->values();

        return LazyCollection::make($rows->all());
    }

    private function memorialIdsInWindow(ReportFilters $filters): Collection
    {
        return $filters->applyTo(MemorialView::query(), 'viewed_at')
            ->distinct()
            ->pluck('memorial_id')
            ->merge($filters->applyTo(Tribute::query(), 'created_at')->distinct()->pluck('memorial_id'))
            ->merge($filters->applyTo(MemorialShare::query(), 'shared_at')->distinct()->pluck('memorial_id'))
            ->unique();
    }
}
