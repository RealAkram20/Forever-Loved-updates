<?php

namespace App\Reports\Admin;

use App\Models\User;
use App\Reports\BaseReport;
use App\Reports\ReportColumn;
use App\Reports\ReportFilters;
use App\Reports\ReportStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\LazyCollection;

/**
 * Who has signed up, and whether they ever came back.
 */
class UsersReport extends BaseReport
{
    public function key(): string
    {
        return 'users';
    }

    public function title(): string
    {
        return 'Users';
    }

    public function description(): string
    {
        return 'Registrations, how they signed up, and who has never returned since.';
    }

    public function group(): string
    {
        return 'People';
    }

    public function dateWindowNote(): ?string
    {
        return 'By registration date.';
    }

    public function availableTo(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin']);
    }

    public function columns(): array
    {
        return [
            ReportColumn::text('name', 'Name', '15%'),
            ReportColumn::text('email', 'Email', '18%'),
            ReportColumn::text('role', 'Role', '10%'),
            ReportColumn::date('registered', 'Registered', '10%'),
            ReportColumn::date('last_login', 'Last seen', '10%'),
            ReportColumn::number('memorials', 'Memorials', '8%'),
            ReportColumn::number('subscriptions', 'Plans', '7%', secondary: true),
            ReportColumn::text('signup_method', 'Sign-in', '9%', secondary: true),
            ReportColumn::text('reseller', 'Belongs to', '13%'),
        ];
    }

    public function summary(ReportFilters $filters): array
    {
        $registered = $this->query($filters)->count();

        $previous = $filters->previousPeriod();
        $registeredBefore = $previous ? $this->query($previous)->count() : null;

        $withMemorial = (clone $this->query($filters))->has('memorials')->count();
        $viaGoogle = (clone $this->query($filters))->whereNotNull('google_id')->count();

        // Signed up and never returned. Only meaningful for accounts old enough to have
        // had the chance — anyone who registered today is not dormant, they are new.
        $neverReturned = User::query()
            ->whereNull('last_login_at')
            ->where('created_at', '<', now()->subDays(7))
            ->count();

        return array_filter([
            ReportStat::make(
                'Registered in period',
                $this->number($registered),
                $registeredBefore !== null ? $this->changeVsPrevious($registered, $registeredBefore) : null,
            ),
            ReportStat::make('Total accounts', $this->number(User::count())),
            ReportStat::make('Created a memorial', $this->number($withMemorial), $this->ratio($withMemorial, $registered).' of new sign-ups'),
            ReportStat::make('Signed up with Google', $this->number($viaGoogle), $this->ratio($viaGoogle, $registered).' of new sign-ups'),
            ReportStat::make(
                'Never signed in again',
                $this->number($neverReturned),
                'All accounts older than a week',
                $neverReturned > 0 ? ReportStat::TONE_WARNING : ReportStat::TONE_NEUTRAL,
            ),
        ]);
    }

    public function total(ReportFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    public function rows(ReportFilters $filters): LazyCollection
    {
        return $this->query($filters)
            ->with(['roles:id,name', 'reseller:id,name'])
            ->withCount(['memorials', 'subscriptions'])
            ->orderByDesc('created_at')
            ->lazy(500)
            ->map(fn (User $user) => $this->shape([
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->pluck('name')->map(fn ($role) => ucfirst($role))->implode(', '),
                'registered' => $user->created_at,
                'last_login' => $user->last_login_at,
                'memorials' => $user->memorials_count,
                'subscriptions' => $user->subscriptions_count,
                'signup_method' => $user->google_id ? 'Google' : ($user->password ? 'Password' : 'Email link'),
                // original_reseller_id is shown when it differs, because a client moved
                // between resellers otherwise looks like they were always where they are.
                'reseller' => $this->attribution($user),
            ]));
    }

    private function attribution(User $user): string
    {
        if (! $user->reseller_id) {
            return 'Platform';
        }

        $name = $user->reseller?->name ?? 'Reseller #'.$user->reseller_id;

        if ($user->original_reseller_id && $user->original_reseller_id !== $user->reseller_id) {
            return $name.' (transferred)';
        }

        return $name;
    }

    private function query(ReportFilters $filters): Builder
    {
        return $filters->applyTo(User::query(), 'created_at');
    }
}
