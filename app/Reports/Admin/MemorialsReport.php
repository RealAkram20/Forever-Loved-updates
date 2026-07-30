<?php

namespace App\Reports\Admin;

use App\Models\Media;
use App\Models\Memorial;
use App\Models\User;
use App\Reports\BaseReport;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Every memorial on the platform, how finished it is, and how much it is carrying.
 */
class MemorialsReport extends BaseReport
{
    public function key(): string
    {
        return 'memorials';
    }

    public function title(): string
    {
        return 'Memorials';
    }

    public function description(): string
    {
        return 'What has been created, how complete it is, and how much storage it uses.';
    }

    public function group(): string
    {
        return 'Content';
    }

    public function dateWindowNote(): ?string
    {
        return 'By the date the memorial was created.';
    }

    public function availableTo(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin']);
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('name', 'Memorial', '15%'),
            ReportColumn::text('owner', 'Owner', '13%'),
            ReportColumn::text('email', 'Owner email', '15%', secondary: true),
            ReportColumn::date('created', 'Created', '8%'),
            ReportColumn::text('status', 'Status', '8%'),
            ReportColumn::bool('is_public', 'Public', '6%'),
            ReportColumn::text('completion', 'Completion', '9%'),
            ReportColumn::bool('has_photo', 'Photo', '6%', secondary: true),
            ReportColumn::bool('has_bio', 'Biography', '7%', secondary: true),
            ReportColumn::number('media', 'Files', '6%', secondary: true),
            ReportColumn::number('storage_mb', 'Storage MB', '8%'),
            ReportColumn::number('views', 'Views', '7%'),
            ReportColumn::number('tributes', 'Tributes', '7%'),
            ReportColumn::number('chapters', 'Chapters', '7%', secondary: true),
            ReportColumn::date('expires', 'Expires', '8%', secondary: true),
            ReportColumn::text('reseller', 'Hosted by', '11%'),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $created = $this->query($filters)->count();
        $total = Memorial::count();
        $active = Memorial::where('status', Memorial::STATUS_ACTIVE)->count();
        $public = Memorial::where('is_public', true)->count();
        $withPhoto = Memorial::whereNotNull('profile_photo_path')->count();
        $complete = Memorial::where('completion_status', Memorial::COMPLETION_COMPLETED)->count();

        $previous = $filters->previousPeriod();
        $createdBefore = $previous ? $this->query($previous)->count() : null;

        return array_filter([
            ReportStat::make(
                'Created in period',
                $this->number($created),
                $createdBefore !== null ? $this->changeVsPrevious($created, $createdBefore) : null,
            ),
            ReportStat::make('Total memorials', $this->number($total), $this->number($active).' active'),
            ReportStat::make('Public', $this->number($public), $this->ratio($public, $total).' of all memorials'),
            ReportStat::make('With a photo', $this->number($withPhoto), $this->ratio($withPhoto, $total).' of all memorials'),
            ReportStat::make('Marked complete', $this->number($complete), $this->ratio($complete, $total).' of all memorials'),
            // Summed across the media table in one query rather than per memorial: this is
            // the figure that decides whether hosting is about to cost more.
            ReportStat::make('Storage used', $this->bytes((int) Media::sum('size'))),
        ]);
    }

    public function total(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        return $this->query($filters)
            ->with(['owner:id,name,email', 'reseller:id,name'])
            ->withCount(['views', 'tributes', 'media', 'storyChapters'])
            ->withSum('media as media_bytes', 'size')
            ->orderByDesc('created_at')
            ->lazy(250)
            ->map(fn (Memorial $memorial) => $this->shape([
                'name' => $memorial->full_name,
                'owner' => $memorial->owner?->name,
                'email' => $memorial->owner?->email,
                'created' => $memorial->created_at,
                'status' => ucfirst((string) $memorial->status),
                'is_public' => (bool) $memorial->is_public,
                'completion' => ucfirst((string) ($memorial->completion_status ?? 'pending')),
                'has_photo' => (bool) $memorial->profile_photo_path,
                'has_bio' => filled($memorial->biography),
                'media' => $memorial->media_count,
                // Rounded to whole MB: a report is not the place for byte-level precision,
                // and "0.03" in a storage column reads as an error rather than a small file.
                'storage_mb' => (int) round(((int) $memorial->media_bytes) / 1048576),
                'views' => $memorial->views_count,
                'tributes' => $memorial->tributes_count,
                'chapters' => $memorial->story_chapters_count,
                'expires' => $memorial->expires_at,
                'reseller' => $memorial->reseller?->name ?? 'Platform',
            ]));
    }

    private function query(ReportFilters $filters): Builder
    {
        return $filters->applyTo(Memorial::query(), 'memorials.created_at');
    }
}
