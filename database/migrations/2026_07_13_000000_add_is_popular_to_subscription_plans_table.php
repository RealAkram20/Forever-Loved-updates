<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('is_popular')->default(false)->after('is_active');
        });

        // The pricing views used to badge every paid plan as "Most Popular".
        // Carry that intent over as a single sensible default — the cheapest
        // paid plan — rather than shipping an upgrade that badges nothing.
        $entry = DB::table('subscription_plans')
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->orderBy('price')
            ->first();

        if ($entry) {
            DB::table('subscription_plans')->where('id', $entry->id)->update(['is_popular' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('is_popular');
        });
    }
};
