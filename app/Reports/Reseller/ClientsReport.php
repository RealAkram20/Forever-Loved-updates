<?php

namespace App\Reports\Reseller;

use App\Models\MemorialView;
use App\Models\Tribute;
use App\Models\User;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use App\Reports\TenantScopedReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * The families this reseller is serving.
 *
 * Scoped to the 'user' role rather than every account carrying the reseller_id, so their
 * own staff are not reported to them as customers.
 */
class ClientsReport extends TenantScopedReport
{
    public function key(): string
    {
        return 'clients';
    }

    public function title(): string
    {
        return 'Clients';
    }

    public function description(): string
    {
        return 'The families you are working with, what they have, and who has stalled.';
    }

    public function group(): string
    {
        return 'Client work';
    }

    public function dateWindowNote(): ?string
    {
        return 'By the date the client account was created.';
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('name', 'Client', '18%'),
            ReportColumn::text('email', 'Email', '22%'),
            ReportColumn::date('joined', 'Added', '11%'),
            ReportColumn::date('last_seen', 'Last signed in', '12%'),
            ReportColumn::number('memorials', 'Memorials', '9%'),
            ReportColumn::number('views', 'Visits received', '11%'),
            ReportColumn::number('tributes', 'Tributes received', '11%'),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $addedInWindow = $this->query($filters)->count();
        $total = $this->clientQuery()->count();
        $withoutMemorial = (clone $this->clientQuery())->doesntHave('memorials')->count();
        $repeat = (clone $this->clientQuery())->has('memorials', '>=', 2)->count();
        $neverSignedIn = (clone $this->clientQuery())
            ->whereNull('last_login_at')
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        return [
            ReportStat::make('Added in period', $this->number($addedInWindow)),
            ReportStat::make('Clients in total', $this->number($total)),
            ReportStat::make(
                'No memorial yet',
                $this->number($withoutMemorial),
                $withoutMemorial > 0 ? 'Intake started but not finished' : null,
                $withoutMemorial > 0 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make(
                'Never signed in',
                $this->number($neverSignedIn),
                'Invited more than a week ago',
                $neverSignedIn > 0 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
            ReportStat::make('Returning clients', $this->number($repeat), 'Two or more memorials'),
        ];
    }

    public function total(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        return $this->query($filters)
            ->withCount('memorials')
            ->orderByDesc('created_at')
            ->lazy(250)
            ->map(function (User $client) {
                // Their own memorials only — a client who moved here from another reseller
                // keeps memorials we do not host, and counting those would inflate the
                // engagement we appear to have produced for them.
                $memorialIds = $client->memorials()
                    ->where('reseller_id', $this->reseller->id)
                    ->select('id');

                return $this->shape([
                    'name' => $client->name,
                    'email' => $client->email,
                    'joined' => $client->created_at,
                    'last_seen' => $client->last_login_at,
                    'memorials' => $client->memorials_count,
                    'views' => MemorialView::whereIn('memorial_id', $memorialIds)->count(),
                    'tributes' => Tribute::whereIn('memorial_id', $memorialIds)->count(),
                ]);
            });
    }

    private function clientQuery(): Builder
    {
        return User::query()
            ->where('reseller_id', $this->reseller->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'user'));
    }

    private function query(ReportFilters $filters): Builder
    {
        return $filters->applyTo($this->clientQuery(), 'users.created_at');
    }
}
