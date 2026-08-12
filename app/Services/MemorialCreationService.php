<?php

namespace App\Services;

use App\Exceptions\ResellerCapacityExceededException;
use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one place a memorial comes into existence.
 *
 * Three screens create memorials — the dashboard form, the public signup wizard, and
 * the reseller intake — and until this class existed each carried its own copy of the
 * slug generator, the title/full-name composition, the relation sync and the template
 * biography fallback, drifting in small ways (one slug generator numbered collisions
 * from -1, another from -2). Worse, only the reseller intake enforced the tier's
 * memorial allowance, so staff could route around their own quota by using the
 * dashboard form. Creation logic lives here now; controllers keep only their
 * request/response concerns.
 */
class MemorialCreationService
{
    private const RELATION_KEYS = ['companies', 'co_founders', 'children', 'spouses', 'parents', 'siblings', 'education'];

    /**
     * Create a memorial owned by $owner from validated data.
     *
     * $overrides are trusted, caller-supplied attributes (reseller_id, status, …) that
     * win over both $data and the computed defaults. Tenant attribution otherwise
     * relies on Memorial::booted()'s creating hook, which copies the owner's
     * reseller_id — the overrides exist for the reseller intake, which stamps its
     * tenant explicitly rather than trusting a lookup at insert time.
     *
     * @throws ResellerCapacityExceededException when the effective tenant is out of quota
     */
    public function create(User $owner, array $data, ?SubscriptionPlan $plan = null, array $overrides = []): Memorial
    {
        $this->assertTenantHasCapacity($owner, $overrides);

        $fullName = trim(implode(' ', array_filter([
            $data['first_name'] ?? '',
            $data['middle_name'] ?? '',
            $data['last_name'] ?? '',
        ]))) ?: trim((string) ($data['full_name'] ?? ''));

        $planLabel = $plan
            ? ($plan->isFree() ? Memorial::PLAN_FREE : Memorial::PLAN_PAID)
            : ($data['plan'] ?? ((($data['theme'] ?? null) === 'premium') ? Memorial::PLAN_PAID : Memorial::PLAN_FREE));

        $attributes = array_merge(
            collect($data)->only((new Memorial)->getFillable())->except(self::RELATION_KEYS)->all(),
            [
                'user_id' => $owner->id,
                'full_name' => $fullName,
                'title' => 'In Loving Memory of '.$fullName,
                'slug' => $this->uniqueSlug($fullName),
                'theme' => $data['theme'] ?? $planLabel,
                'plan' => $planLabel,
                'biography' => trim((string) ($data['biography'] ?? '')) ?: null,
                'completion_status' => Memorial::COMPLETION_PENDING,
                'is_public' => (bool) ($data['is_public'] ?? true),
            ],
            $overrides
        );

        $memorial = DB::transaction(function () use ($attributes, $data) {
            $memorial = Memorial::create($attributes);
            $this->syncRelations($memorial, $data);

            return $memorial;
        });

        // A memorial with an empty biography reads as abandoned to visitors, so a
        // structured template one is generated from whatever facts were provided.
        // Best-effort: its failure must never lose the creation itself.
        if (empty($memorial->biography)) {
            try {
                $biography = app(TemplateBioGeneratorService::class)->generateStructured($memorial);
                if ($biography && trim($biography) !== '') {
                    $memorial->update(['biography' => $biography]);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $memorial;
    }

    /**
     * Record the memorial's plan as an actual UserSubscription and stamp the memorial.
     *
     * Free plans get the same instant, never-ending subscription the signup wizard has
     * always granted. The 'offline' gateway is for reseller intake choosing a paid
     * plan: the reseller collects from the family directly, so the entitlements start
     * now and revenue reports can tell these apart from gateway-collected payments.
     */
    public function attachPlanSubscription(Memorial $memorial, SubscriptionPlan $plan, ?string $gateway = null, ?string $reference = null): UserSubscription
    {
        $startsAt = now();
        // Same interval → end-date mapping as PaymentResultProcessor uses for
        // gateway-collected subscriptions, so an offline-recorded plan renews on
        // the same schedule as a paid-online one.
        $endsAt = $plan->isFree() ? null : match ($plan->interval ?? 'monthly') {
            'monthly' => $startsAt->copy()->addMonth(),
            'yearly' => $startsAt->copy()->addYear(),
            default => null,
        };

        $subscription = UserSubscription::create([
            'user_id' => $memorial->user_id,
            'memorial_id' => $memorial->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'active',
            'payment_gateway' => $gateway,
            'payment_reference' => $reference,
        ]);

        $memorial->update([
            'plan' => $plan->isFree() ? Memorial::PLAN_FREE : Memorial::PLAN_PAID,
            'subscription_plan_id' => $plan->id,
            'user_subscription_id' => $subscription->id,
        ]);

        return $subscription;
    }

    /**
     * Slug from the person's name, suffixed -1, -2, … on collision. $excludeMemorialId
     * lets an update regenerate for the same record without colliding with itself.
     */
    public function uniqueSlug(string $fullName, ?int $excludeMemorialId = null): string
    {
        $baseSlug = Str::slug($fullName);
        $slug = $baseSlug;
        $suffix = 0;

        while (
            Memorial::where('slug', $slug)
                ->when($excludeMemorialId, fn ($q) => $q->where('id', '!=', $excludeMemorialId))
                ->exists()
        ) {
            $suffix++;
            $slug = $baseSlug.'-'.$suffix;
        }

        return $slug;
    }

    /**
     * Sync relational data (companies, co-founders, children, spouses, parents,
     * siblings, education). Only syncs relations present as keys in $data, so partial
     * updates leave the others untouched.
     */
    public function syncRelations(Memorial $memorial, array $data): void
    {
        if (array_key_exists('companies', $data)) {
            $memorial->notableCompanies()->delete();
            foreach (array_filter($data['companies'] ?? [], fn ($c) => ! empty(trim($c['company_name'] ?? ''))) as $i => $c) {
                $memorial->notableCompanies()->create(['company_name' => trim($c['company_name']), 'sort_order' => $i]);
            }
        }

        if (array_key_exists('co_founders', $data)) {
            $memorial->coFounders()->delete();
            foreach (array_filter($data['co_founders'] ?? [], fn ($c) => ! empty(trim($c['name'] ?? ''))) as $i => $c) {
                $memorial->coFounders()->create(['name' => trim($c['name']), 'sort_order' => $i]);
            }
        }

        if (array_key_exists('children', $data)) {
            $memorial->children()->delete();
            foreach (array_filter($data['children'] ?? [], fn ($c) => ! empty(trim($c['child_name'] ?? ''))) as $c) {
                $memorial->children()->create([
                    'child_name' => trim($c['child_name']),
                    'birth_year' => ! empty($c['birth_year']) ? (int) $c['birth_year'] : null,
                ]);
            }
        }

        if (array_key_exists('spouses', $data)) {
            $memorial->spouses()->delete();
            foreach (array_filter($data['spouses'] ?? [], fn ($c) => ! empty(trim($c['spouse_name'] ?? ''))) as $c) {
                $memorial->spouses()->create([
                    'spouse_name' => trim($c['spouse_name']),
                    'marriage_start_year' => ! empty($c['marriage_start_year']) ? (int) $c['marriage_start_year'] : null,
                    'marriage_end_year' => ! empty($c['marriage_end_year']) ? (int) $c['marriage_end_year'] : null,
                ]);
            }
        }

        if (array_key_exists('parents', $data)) {
            $memorial->parents()->delete();
            foreach (array_filter($data['parents'] ?? [], fn ($c) => ! empty(trim($c['parent_name'] ?? ''))) as $c) {
                $memorial->parents()->create([
                    'parent_name' => trim($c['parent_name']),
                    'relationship_type' => in_array($c['relationship_type'] ?? '', ['biological', 'adoptive']) ? $c['relationship_type'] : 'biological',
                ]);
            }
        }

        if (array_key_exists('siblings', $data)) {
            $memorial->siblings()->delete();
            foreach (array_filter($data['siblings'] ?? [], fn ($c) => ! empty(trim($c['sibling_name'] ?? ''))) as $c) {
                $memorial->siblings()->create(['sibling_name' => trim($c['sibling_name'])]);
            }
        }

        if (array_key_exists('education', $data)) {
            $memorial->education()->delete();
            foreach (array_filter($data['education'] ?? [], fn ($c) => ! empty(trim($c['institution_name'] ?? ''))) as $c) {
                $memorial->education()->create([
                    'institution_name' => trim($c['institution_name']),
                    'start_year' => ! empty($c['start_year']) ? (int) $c['start_year'] : null,
                    'end_year' => ! empty($c['end_year']) ? (int) $c['end_year'] : null,
                    'degree' => ! empty($c['degree']) ? trim($c['degree']) : null,
                ]);
            }
        }
    }

    /**
     * The tier allowance is billed per reseller, so it must hold no matter which door
     * a creation comes through — reseller intake, the dashboard form, or the signup
     * wizard on a reseller's site. Effective tenant mirrors what Memorial::booted()
     * will stamp: an explicit override wins, otherwise the owner's own reseller.
     */
    private function assertTenantHasCapacity(User $owner, array $overrides): void
    {
        $resellerId = array_key_exists('reseller_id', $overrides)
            ? $overrides['reseller_id']
            : $owner->reseller_id;

        if (! $resellerId) {
            return;
        }

        $reseller = Reseller::with('tier')->find($resellerId);

        if ($reseller && ! $reseller->hasMemorialCapacity()) {
            throw new ResellerCapacityExceededException($reseller);
        }
    }
}
