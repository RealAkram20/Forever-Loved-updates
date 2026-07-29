<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->after('id')->constrained('resellers')->nullOnDelete();
            $table->index(['reseller_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Foreign key first. MySQL drops the auto-created single-column index once the
            // composite covers it, making the composite the only index backing the
            // constraint — dropping it first fails with "needed in a foreign key
            // constraint" and leaves the schema half-reverted.
            $table->dropForeign(['reseller_id']);
            $table->dropIndex(['reseller_id', 'is_active']);
            $table->dropColumn('reseller_id');
        });
    }
};
