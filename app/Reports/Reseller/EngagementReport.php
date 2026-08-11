<?php

namespace App\Reports\Reseller;

use App\Models\Memorial;
use App\Models\MemorialShare;
use App\Models\MemorialView;
use App\Models\Tribute;
use App\Models\User;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use App\Reports\TenantScopedReport;
use Illuminate\Support\LazyCollection;

/**
 * How much attention the memorials this reseller hosts are getting.
 *
 * The one reseller report behind a tier flag, and on the same flag as the Analytics page
 * rather than a new one: it is the same data, and gating the two differently would mean a
 * reseller could be sold the chart but not the export of it.
 */
class EngagementReport extends TenantScopedReport
{
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
        return 'Visits, visitors and tributes across every memorial you host.';
    }

    public function group(): string
    {
        return 'Client work';
    }

    public function dateWindowNote(): ?string
    {
        return 'By the date each visit, tribute or share happened.';
    }

    public function unlockedFor(User $user): bool
    {
        return $this->tierAllows('business_analytics');
    }

    public function lockedMessage(): ?string
    {
        return 'Engagement reporting shows visitor numbers and tribute activity for every memorial you host — the figures families ask for, ready to print or email. Get in touch to add it to your plan.';
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('memorial', 'Memorial', '24%'),
            ReportColumn::text('client', 'Client', '18%'),
            ReportColumn::number('views', 'Visits', '11%'),
            ReportColumn::number('visitors', 'Unique visitors', '13%'),
            ReportColumn::number('tributes', 'Tributes', '11%'),
            ReportColumn::number('shares', 'Shares', '10%'),
            ReportColumn::date('last_view', 'Last visit', '13%'),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $memorialIds = $this->memorialIds();

        $views = $filters->applyTo(MemorialView::whereIn('memorial_id', $memorialIds), 'viewed_at')->count();
        $visitors = $filters->applyTo(MemorialView::whereIn('memorial_id', $memorialIds), 'viewed_at')
            ->distinct('visitor_hash')->count('visitor_hash');
        $tributes = $filters->applyTo(Tribute::whereIn('memorial_id', $memorialIds), 'created_at')->count();
        $shares = $filters->applyTo(MemorialShare::whereIn('memorial_id', $memorialIds), 'shared_at')->count();

        $previous = $filters->previousPeriod();
        $viewsBefore = $previous
            ? $previous->applyTo(MemorialView::whereIn('memorial_id', $memorialIds), 'viewed_at')->count()
            : null;

        return array_filter([
            ReportStat::make(
                'Visits',
                $this->number($views),
                $viewsBefore !== null ? $this->changeVsPrevious($views, $viewsBefore) : null,
            ),
            ReportStat::make('Unique visitors', $this->number($visitors), $views > 0 ? $this->ratio($visitors, $views).' of visits' : null),
            ReportStat::make('Tributes left', $this->number($tributes)),
            ReportStat::make('Shares', $this->number($shares)),
            ReportStat::make('Visits, all time', $this->number(MemorialView::whereIn('memorial_id', $memorialIds)->count())),
        ]);
    }

    public function total(ReportFilters $filters): int
    {
        return $this->memorialQuery()->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        $memorialIds = $this->memorialIds();

        // One grouped pass per metric, joined in PHP — the alternative is a correlated
        // subquery per memorial per column.
        $views = $filters->applyTo(MemorialView::whereIn('memorial_id', $memorialIds), 'viewed_at')
            ->selectRaw('memorial_id, COUNT(*) as views, COUNT(DISTINCT visitor_hash) as visitors, MAX(viewed_at) as last_view')
            ->groupBy('memorial_id')
            ->get()
            ->keyBy('memorial_id');

        $tributes = $filters->applyTo(Tribute::whereIn('memorial_id', $memorialIds), 'created_at')
            ->selectRaw('memorial_id, COUNT(*) as total')
            ->groupBy('memorial_id')
            ->pluck('total', 'memorial_id');

        $shares = $filters->applyTo(MemorialShare::whereIn('memorial_id', $memorialIds), 'shared_at')
            ->selectRaw('memorial_id, COUNT(*) as total')
            ->groupBy('memorial_id')
            ->pluck('total', 'memorial_id');

        // Every memorial they host, not only those with activity: a reseller's own list
        // should show the quiet ones too, since "nobody has visited this yet" is exactly
        // the thing they would want to act on.
        $rows = $this->memorialQuery()
            ->with('owner:id,name')
            ->get()
            ->map(fn (Memorial $memorial) => $this->shape([
                'memorial' => $memorial->full_name,
                'client' => $memorial->owner?->name,
                'views' => (int) ($views[$memorial->id]->views ?? 0),
                'visitors' => (int) ($views[$memorial->id]->visitors ?? 0),
                'tributes' => (int) ($tributes[$memorial->id] ?? 0),
                'shares' => (int) ($shares[$memorial->id] ?? 0),
                'last_view' => $views[$memorial->id]->last_view ?? null,
            ]))
            ->sortByDesc('views')
            ->values();

        return LazyCollection::make($rows->all());
    }
}
