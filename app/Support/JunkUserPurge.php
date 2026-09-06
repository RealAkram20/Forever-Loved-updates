<?php

namespace App\Support;

use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finding and removing the accounts the 2026-09-04 relay left behind.
 *
 * The attack registered thousands of users whose *name* was a phishing message and whose
 * *email* was a stranger's. HumanName now refuses those at every door, but the rows already
 * created are still in the table, still listed in admin, and still valid recipients if anything
 * ever re-sends to them. This is the one place that knows what one of those rows looks like
 * and what it is safe to do about it.
 *
 * One definition, two consumers: the admin Users screen (a filter, and a bulk delete) and the
 * `users:purge-suspicious` command for the volumes a browser request cannot get through. If
 * the filter and the purge disagreed about what "suspicious" means, an admin would delete
 * something the screen never showed them.
 *
 * **What it will not delete, whatever it is told.** The users table cascades: deleting a row
 * deletes that person's memorials, subscriptions and payment orders with it. So every candidate
 * is refused if it owns a memorial, has a payment order, holds a staff role, is protected, or
 * is the actor. A junk account has none of those; a real family has at least one. The refusal
 * is per row and reported, never an abort — one protected row must not save the other 499.
 */
final class JunkUserPurge
{
    /**
     * How many a single web request will remove. Enough to clear an afternoon's attack in a
     * few clicks; small enough that the request returns before a proxy gives up on it. The
     * artisan command has no such cap.
     */
    public const WEB_BATCH = 500;

    /**
     * Longer than any real name. Mirrors HumanName::MAX, which is the rule that now stops these
     * at the door — the two should move together.
     */
    private const MAX_NAME = 80;

    /**
     * Link-shaped, as a SQL REGEXP. Same three shapes HumanName refuses: a scheme, a `www.`, and
     * the bare `host.tld/` the attack actually used. MySQL REGEXP is case-insensitive on text
     * columns; the sqlite function registered for tests is written to match.
     */
    private const LINK_PATTERNS = [
        'https?://',
        '(^|[^a-z0-9])www\.',
        '[a-z0-9-]+\.[a-z]{2,}[/?]',
    ];

    /**
     * Narrow a users query to rows that look like the relay's leftovers.
     *
     * Link-shaped name, or a name too long to be one, or a name with a line break in it — and
     * no memorials. The memorial condition is part of the definition, not only a guard on
     * delete: a real person who pasted a URL into their name once still has a page that must
     * not be listed as junk.
     */
    public static function scope(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $q) {
                foreach (self::LINK_PATTERNS as $pattern) {
                    $q->orWhere('name', 'REGEXP', $pattern);
                }

                // Characters, not bytes: an 80-character name in a non-Latin script is many
                // more bytes and must not be flagged for it. MySQL spells that CHAR_LENGTH;
                // sqlite (the test database) has only LENGTH, which is already per-character
                // for text.
                $len = $q->getConnection()->getDriverName() === 'sqlite' ? 'LENGTH' : 'CHAR_LENGTH';
                $q->orWhereRaw("{$len}(name) > ?", [self::MAX_NAME])
                    ->orWhere('name', 'like', "%\n%")
                    ->orWhere('name', 'like', "%\r%");
            })
            ->whereDoesntHave('memorials');
    }

    public static function query(): Builder
    {
        return self::scope(User::query());
    }

    /**
     * Why this row must be left alone, or null if it may go.
     *
     * Checked in the order a mistake would be most expensive. `$actor` is null when run from
     * the console, where there is nobody to be "yourself" — every other refusal still applies.
     */
    public static function reasonToSkip(User $user, ?User $actor): ?string
    {
        if ($actor && $user->id === $actor->id) {
            return 'self';
        }

        if ($user->memorials()->exists()) {
            return 'memorials';
        }

        if (PaymentOrder::where('user_id', $user->id)->exists()) {
            return 'payments';
        }

        if ($user->hasAnyRole(['admin', 'super-admin', 'reseller'])) {
            return 'staff';
        }

        if (! ProtectedRoles::actorMayManage($actor, $user)) {
            return 'protected';
        }

        return null;
    }

    /**
     * Delete what may be deleted, count what may not, and say why.
     *
     * @param  iterable<User>  $users
     * @return array{deleted:int, skipped:array<string,int>}
     */
    public static function purge(iterable $users, ?User $actor): array
    {
        $summary = ['deleted' => 0, 'skipped' => []];

        foreach ($users as $user) {
            if ($reason = self::reasonToSkip($user, $actor)) {
                $summary['skipped'][$reason] = ($summary['skipped'][$reason] ?? 0) + 1;

                continue;
            }

            $user->delete();
            $summary['deleted']++;
        }

        return $summary;
    }

    /**
     * The summary as one sentence an admin can act on.
     */
    public static function describe(array $summary, ?int $remaining = null): string
    {
        $labels = [
            'self' => 'your own account',
            'memorials' => 'owns memorials',
            'payments' => 'has payment history',
            'staff' => 'staff accounts',
            'protected' => 'protected accounts',
        ];

        $parts = ['Deleted '.number_format($summary['deleted']).' '.($summary['deleted'] === 1 ? 'user' : 'users').'.'];

        if ($summary['skipped'] !== []) {
            $why = [];

            foreach ($summary['skipped'] as $reason => $count) {
                $why[] = number_format($count).' '.($labels[$reason] ?? $reason);
            }

            $parts[] = 'Skipped '.implode(', ', $why).'.';
        }

        if ($remaining) {
            $parts[] = number_format($remaining).' suspicious '.($remaining === 1 ? 'account remains' : 'accounts remain').
                ' — run again, or use `php artisan users:purge-suspicious` for the whole set.';
        }

        return implode(' ', $parts);
    }
}
