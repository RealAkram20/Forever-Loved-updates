<?php

namespace App\Reports;

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base for every reseller-facing report.
 *
 * The reseller is injected by the container — bound by EnsureResellerActive from the
 * authenticated user's own reseller_id — and there is deliberately no setter and no path
 * by which a tenant id could arrive from the request. A subclass that wants to scope a
 * query calls $this->reseller; it has nothing else available to scope by, so "did this
 * report remember to filter by tenant?" is answered by the type, not by review.
 */
abstract class TenantScopedReport extends BaseReport
{
    public function __construct(protected readonly Reseller $reseller)
    {
        // Reseller is a concrete, newable class, so the container will happily hand out a
        // blank instance when nothing has been bound — outside a request, or if
        // EnsureResellerActive is ever dropped from these routes. A blank one has a null
        // id, which would scope every query to `reseller_id is null`: the platform's own
        // records, silently served to a reseller. Fail loudly instead.
        if (! $reseller->exists) {
            throw new \LogicException(
                'A tenant-scoped report was built without a resolved Reseller. It must be '
                .'bound into the container by EnsureResellerActive before the registry runs.'
            );
        }
    }

    /**
     * Subquery of the memorial ids this reseller hosts. Used rather than a fetched array
     * so the scope stays a single SQL statement no matter how many memorials they hold.
     *
     * Built from the model rather than $reseller->memorials(), which returns a relation:
     * whereIn() takes a Builder, and the columns are qualified because these subqueries
     * land inside joined statements where reseller_id is ambiguous.
     */
    protected function memorialIds(): Builder
    {
        return $this->memorialQuery()->select('memorials.id');
    }

    protected function memorialQuery(): Builder
    {
        return Memorial::query()->where('memorials.reseller_id', $this->reseller->id);
    }

    protected function clientIds(): Builder
    {
        return User::query()
            ->where('users.reseller_id', $this->reseller->id)
            ->select('users.id');
    }

    /**
     * Reseller staff and the platform's own super-admins (while impersonating) reach these.
     * A reseller whose account is suspended never gets here — EnsureResellerActive stops
     * them at the route.
     */
    public function availableTo(User $user): bool
    {
        return $user->reseller_id === $this->reseller->id;
    }

    /**
     * Tier gating, for the reports that have it. Overridden by the ones that do; the rest
     * inherit "always unlocked", because a reseller's own operational records are not a
     * paid capability.
     */
    protected function tierAllows(string $feature): bool
    {
        return $this->reseller->tierAllows($feature);
    }
}
