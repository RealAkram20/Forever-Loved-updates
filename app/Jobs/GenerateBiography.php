<?php

namespace App\Jobs;

use App\Helpers\PlanLimitsHelper;
use App\Models\Memorial;
use App\Services\BiographyGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Runs AI biography generation off the request thread. The edit page polls
 * the cache-backed state at ai_bio_request:{uuid} (written here) until it
 * flips to completed/failed. structuredData is snapshotted at dispatch time,
 * so the user's form input survives whatever happens to this job.
 */
class GenerateBiography implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const CACHE_PREFIX = 'ai_bio_request:';
    public const CACHE_TTL_MINUTES = 30;

    /** Single attempt: quota reserved up front; a retry would double-charge providers. */
    public int $tries = 1;

    /** Provider HTTP timeouts are ≤60s + template time; cap the whole run. */
    public int $timeout = 120;

    public function __construct(
        public int $memorialId,
        public array $structuredData,
        public string $requestId,
        public bool $noCache,
        public array $quota,
    ) {
    }

    public function handle(): void
    {
        $memorial = Memorial::find($this->memorialId);
        if (! $memorial) {
            $this->writeState(['status' => 'failed', 'message' => 'Memorial no longer exists.']);

            return;
        }

        $result = app(BiographyGenerator::class)->generate($memorial, $this->structuredData, $this->noCache);

        if (! $result['success']) {
            PlanLimitsHelper::releaseAiBioUsage($memorial);
            $this->writeState(['status' => 'failed', 'message' => $result['message']]);

            return;
        }

        unset($result['success']);
        $this->writeState(['status' => 'completed'] + $result + $this->quota);
    }

    public function failed(?\Throwable $e): void
    {
        // Unexpected crash (timeout, OOM) — refund the slot and unblock the poller.
        $memorial = Memorial::find($this->memorialId);
        if ($memorial) {
            PlanLimitsHelper::releaseAiBioUsage($memorial);
        }

        $this->writeState(['status' => 'failed', 'message' => 'Generation took too long or failed unexpectedly. Please try again.']);

        Log::warning('GenerateBiography job failed', [
            'memorial_id' => $this->memorialId,
            'request_id' => $this->requestId,
            'error' => $e?->getMessage(),
        ]);
    }

    private function writeState(array $state): void
    {
        Cache::put(
            self::CACHE_PREFIX.$this->requestId,
            $state + ['memorial_id' => $this->memorialId],
            now()->addMinutes(self::CACHE_TTL_MINUTES)
        );
    }
}
