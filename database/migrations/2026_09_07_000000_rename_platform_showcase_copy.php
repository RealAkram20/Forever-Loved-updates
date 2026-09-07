<?php

use App\Support\PlatformShowcaseCopy;
use Illuminate\Database\Migrations\Migration;

/**
 * "Featured" → "Featured Memorials", "Memorial Inspiration" → "Remembering Loved Ones", and
 * the paragraph under them removed. On the platform homepage's showcase row.
 *
 * A migration rather than a script because the heading is stored data, not code: a change to
 * SiteLayoutService reaches only a fresh install, and production keeps whatever it saved on
 * day one. Deploys run migrations, so this lands when the deploy does — the first time this
 * copy has changed without someone remembering to run something on the server afterwards.
 *
 * Reversible. `down()` writes the previous copy back, paragraph included.
 */
return new class extends Migration
{
    public function up(): void
    {
        PlatformShowcaseCopy::apply(PlatformShowcaseCopy::CURRENT);
    }

    public function down(): void
    {
        PlatformShowcaseCopy::apply(PlatformShowcaseCopy::PREVIOUS);
    }
};
