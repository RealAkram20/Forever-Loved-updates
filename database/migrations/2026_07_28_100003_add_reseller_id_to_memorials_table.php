<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->foreignId('reseller_id')->nullable()->after('user_id')->constrained('resellers')->nullOnDelete();
            $table->foreignId('original_reseller_id')->nullable()->after('reseller_id')->constrained('resellers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->dropForeign(['reseller_id']);
            $table->dropForeign(['original_reseller_id']);
            $table->dropColumn(['reseller_id', 'original_reseller_id']);
        });
    }
};
