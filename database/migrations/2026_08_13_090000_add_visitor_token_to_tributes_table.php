<?php

use App\Support\VisitorToken;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where an anonymous tap's "one per person" lives.
 *
 * Until now that rule was keyed on `user_id`, and it only worked because every signed-out
 * visitor who tapped a card was silently turned into a registered user first. Taps ask for
 * nothing now, so the key moves to a hashed browser token.
 *
 * Nothing is backfilled. Rows written before this are keyed on the account the old flow
 * created for their author, which still identifies that person correctly — so their tallies
 * are right and stay right. The column simply starts out null for all of them.
 *
 * @see VisitorToken
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tributes', function (Blueprint $table) {
            $table->string('visitor_token', 64)->nullable()->after('user_id');

            // The exact shape of the duplicate lookup: this memorial, this gesture, this
            // browser. Without it every tap on a well-visited memorial scans the table.
            $table->index(['memorial_id', 'type', 'visitor_token'], 'tributes_visitor_dedupe_index');
        });
    }

    public function down(): void
    {
        Schema::table('tributes', function (Blueprint $table) {
            $table->dropIndex('tributes_visitor_dedupe_index');
            $table->dropColumn('visitor_token');
        });
    }
};
