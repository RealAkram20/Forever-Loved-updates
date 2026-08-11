<?php

namespace App\Reports\Reseller;

use App\Models\Memorial;
use App\Models\MemorialView;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use App\Reports\TenantScopedReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Every memorial this reseller hosts, and which families need chasing.
 *
 * The two columns that earn their place are "Completion" and "Last activity" — together
 * they are the follow-up list, which is the operational job this report actually does.
 */
class ClientMemorialsReport extends TenantScopedReport
{
    public function key(): string
    {
        return 'memorials';
    }

    public function title(): string
    {
        return 'Client memorials';
    }

    public function description(): string
    {
        return 'The memorials you host, how finished each one is, and when someone last visited.';
    }

    public function group(): string
    {
        return 'Client work';
    }

    public function dateWindowNote(): ?string
    {
        return 'By the date the memorial was created.';
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('name', 'Memorial', '16%'),
            ReportColumn::text('client', 'Client', '13%'),
            ReportColumn::text('email', 'Client email', '16%', secondary: true),
            ReportColumn::date('created', 'Created', '9%'),
            ReportColumn::text('status', 'Status', '8%'),
            ReportColumn::text('completion', 'Completion', '9%'),
            ReportColumn::bool('has_photo', 'Photo', '6%', secondary: true),
            ReportColumn::number('media', 'Files', '6%', secondary: true),
            ReportColumn::number('storage_mb', 'Storage MB', '8%', secondary: true),
            ReportColumn::number('views', 'Visits', '7%'),
            ReportColumn::number('tributes', 'Tributes', '7%'),
            ReportColumn::date('last_activity', 'Last activity', '10%'),
            ReportColumn::text('address', 'Web address', '16%', secondary: true),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $createdInWindow = $this->query($filters)->count();
        $all = $this->memorialQuery();

        $total = (clone $all)->count();
        $active = (clone $all)->where('status', Memorial::STATUS_ACTIVE)->count();
        $complete = (clone $all)->where('completion_status', Memorial::COMPLETION_COMPLETED)->count();
        $noPhoto = (clone $all)->whereNull('profile_photo_path')->count();

        $views = MemorialView::whereIn('memorial_id', $this->memorialIds())->count();

        return [
            ReportStat::make('Created in period', $this->number($createdInWindow)),
            ReportStat::make('Memorials hosted', $this->number($total), $this->number($active).' active'),
            ReportStat::make('Marked complete', $this->number($complete), $this->ratio($complete, $total).' of all yours'),
            ReportStat::make(
                'Still missing a photo',
                $this->number($noPhoto),
                $noPhoto > 0 ? 'Worth a follow-up call' : null,
                $noPhoto > 0 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make('Visits, all time', $this->number($views)),
            ReportStat::make('Storage used', $this->bytes($this->reseller->storageUsedBytes())),
        ];
    }

    public function total(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        return $this->query($filters)
            ->with('owner:id,name,email')
            ->withCount(['views', 'tributes', 'media'])
            ->withSum('media as media_bytes', 'size')
            ->withMax('views as last_view', 'viewed_at')
            ->withMax('tributes as last_tribute', 'created_at')
            ->orderByDesc('memorials.created_at')
            ->lazy(250)
            ->map(fn (Memorial $memorial) => $this->shape([
                'name' => $memorial->full_name,
                'client' => $memorial->owner?->name,
                'email' => $memorial->owner?->email,
                'created' => $memorial->created_at,
                'status' => ucfirst((string) $memorial->status),
                'completion' => ucfirst((string) ($memorial->completion_status ?? 'pending')),
                'has_photo' => (bool) $memorial->profile_photo_path,
                'media' => $memorial->media_count,
                'storage_mb' => (int) round(((int) $memorial->media_bytes) / 1048576),
                'views' => $memorial->views_count,
                'tributes' => $memorial->tributes_count,
                // The later of a visit and a tribute: either counts as the family's page
                // still being alive, and taking only views would call a memorial dormant
                // on the day someone left a message on it.
                'last_activity' => $this->lastActivity($memorial),
                'address' => $this->reseller->publicUrlForSlug($memorial->slug),
            ]));
    }

    private function lastActivity(Memorial $memorial): ?string
    {
        return collect([$memorial->last_view, $memorial->last_tribute])
            ->filter()
            ->max();
    }

    private function query(ReportFilters $filters): Builder
    {
        return $filters->applyTo($this->memorialQuery(), 'memorials.created_at');
    }
}
