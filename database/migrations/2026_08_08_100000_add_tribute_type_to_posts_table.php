<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The marker on a story.
 *
 * A memorial page used to hold two different written things — a tribute and a chapter —
 * in two tabs, and a visitor had to work out which one they meant before they could say
 * anything at all. There is now one thing: a story. Whoever writes it may mark it as a
 * flower, a candle or a prayer; leaving it unmarked is the common case and costs nothing.
 *
 * Nullable rather than defaulted to 'story': the absence of a marker is the absence of a
 * marker, and a NULL says so without inventing a fourth type that would then have to be
 * filtered out of every count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('tribute_type', 16)->nullable()->after('type');
            $table->index(['memorial_id', 'tribute_type']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['memorial_id', 'tribute_type']);
            $table->dropColumn('tribute_type');
        });
    }
};