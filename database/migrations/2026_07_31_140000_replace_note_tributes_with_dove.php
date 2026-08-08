<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The tribute palette becomes flower / candle / dove, replacing 'note'.
     *
     * Existing note tributes are re-typed rather than deleted: the message a mourner wrote
     * is the valuable part and it renders identically under the new type — only the icon and
     * label change. Leaving them as 'note' would have stranded them, because nothing in the
     * new UI renders that type or offers a filter pill for it.
     */
    public function up(): void
    {
        DB::table('tributes')->where('type', 'note')->update(['type' => 'dove']);
    }

    /**
     * Lossy by nature: this is a merge, so rolling back returns *every* dove to a note,
     * including ones created after this ran. There is no column recording which were
     * originally notes, and adding one to serve a rollback that re-splits them would
     * outlive the rollback itself.
     */
    public function down(): void
    {
        DB::table('tributes')->where('type', 'dove')->update(['type' => 'note']);
    }
};
