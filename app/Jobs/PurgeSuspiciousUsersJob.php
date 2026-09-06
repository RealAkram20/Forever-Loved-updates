<?php

namespace App\Jobs;

use App\Support\JunkUserPurge;
use App\Support\ReliableDispatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Remove every suspicious account, server-side, without an admin clicking 500 at a time.
 *
 * The bulk delete on the Users screen was capped per request so a browser did not sit on a
 * request long enough for a proxy to give up. That cap made sense for the mechanism and none
 * for the person: an attack that left tens of thousands of rows should not need dozens of
 * clicks to undo. This is the same purge — same definition, same refusals, via JunkUserPurge
 * — moved off the request.
 *
 * **Slices, and a re-dispatch.** Each run deletes at most SLICE rows and then, if any remain,
 * dispatches a fresh copy of itself. That shape works under both of ReliableDispatch's
 * behaviours: on a healthy queue it is a chain of short jobs that survives a worker restart
 * between links; on the inline fallback it is one request doing the work in bounded pieces
 * with every slice committed as it goes. Progress is never lost; at worst it pauses.
 *
 * **What it refuses is unchanged.** Memorial owners, payers, staff, protected — every row
 * still passes JunkUserPurge::reasonToSkip. There is no actor here, so "self" does not apply
 * and nothing else is relaxed.
 */
class PurgeSuspiciousUsersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Deletions per run. Each is a row delete plus a few existence checks — comfortably under
     * a minute at 2000 even on modest hardware, which keeps the inline fallback inside a
     * request and keeps a queued link short enough to be uninteresting.
     */
    public const SLICE = 2000;

    public int $tries = 3;

    public function __construct(
        public int $slice = self::SLICE,
        public int $startedWith = 0,
        public int $deletedSoFar = 0,
    ) {
        if ($this->startedWith === 0) {
            $this->startedWith = JunkUserPurge::query()->count();
        }
    }

    public function handle(): void
    {
        $summary = ['deleted' => 0, 'skipped' => []];

        JunkUserPurge::query()->orderBy('id')->chunkById(200, function ($users) use (&$summary) {
            foreach ($users as $user) {
                if ($summary['deleted'] >= $this->slice) {
                    return false;
                }

                $one = JunkUserPurge::purge([$user], null);
                $summary['deleted'] += $one['deleted'];

                foreach ($one['skipped'] as $reason => $n) {
                    $summary['skipped'][$reason] = ($summary['skipped'][$reason] ?? 0) + $n;
                }
            }
        });

        $total = $this->deletedSoFar + $summary['deleted'];
        $remaining = JunkUserPurge::query()->count();

        // A slice that deleted nothing and still sees rows means everything left is refused
        // (memorial owners with junk-looking names, and the like). Re-dispatching would loop
        // forever over the same rows, so this is a terminal state too.
        $done = $remaining === 0 || $summary['deleted'] === 0;

        Log::info($done ? 'Suspicious-user purge finished' : 'Suspicious-user purge slice done', [
            'started_with' => $this->startedWith,
            'deleted_total' => $total,
            'this_slice' => $summary,
            'remaining' => $remaining,
        ]);

        if (! $done) {
            ReliableDispatch::dispatch(new self($this->slice, $this->startedWith, $total));
        }
    }
}
