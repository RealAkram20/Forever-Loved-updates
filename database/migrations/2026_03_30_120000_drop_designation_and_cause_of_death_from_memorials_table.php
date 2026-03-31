<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->dropColumn([
                'cause_of_death',
                'cause_of_death_private',
                'designation',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->string('designation')->nullable()->after('relationship');
            $table->string('cause_of_death')->nullable()->after('death_country');
            $table->boolean('cause_of_death_private')->default(false)->after('cause_of_death');
        });
    }
};
