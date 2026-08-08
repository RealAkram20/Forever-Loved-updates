<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The third tribute becomes 'prayer' rather than 'dove'.
     *
     * A separate migration instead of editing the note→dove one: that has already run on
     * every environment it is going to run on, so rewriting it in place would leave the
     * existing dove rows behind. This catches them, and on a fresh database it is simply
     * a no-op that runs after the merge.
     */
    public function up(): void
    {
        DB::table('tributes')->where('type', 'dove')->update(['type' => 'prayer']);
    }

    public function down(): void
    {
        DB::table('tributes')->where('type', 'prayer')->update(['type' => 'dove']);
    }
};
