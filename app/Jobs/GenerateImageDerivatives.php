<?php

namespace App\Jobs;

use App\Services\ImageDerivativeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Off the request path on purpose: an upload response should not wait on image
 * scaling, and every consumer of derivatives falls back to the original until this
 * lands (the scheduler drains the queue each minute in production).
 */
class GenerateImageDerivatives implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public string $path)
    {
    }

    public function handle(ImageDerivativeService $derivatives): void
    {
        $derivatives->generate($this->path);
    }
}
