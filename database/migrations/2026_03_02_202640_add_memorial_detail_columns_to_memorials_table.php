<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('full_name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('gender')->nullable()->after('last_name');
            $table->string('relationship')->nullable()->after('gender');
            $table->string('designation')->nullable()->after('relationship');
            $table->text('more_details')->nullable()->after('designation');
            $table->unsignedSmallInteger('birth_year')->nullable()->after('more_details');
            $table->unsignedTinyInteger('birth_month')->nullable()->after('birth_year');
            $table->unsignedTinyInteger('birth_day')->nullable()->after('birth_month');
            $table->string('birth_city')->nullable()->after('birth_day');
            $table->string('birth_state')->nullable()->after('birth_city');
            $table->string('birth_country')->nullable()->after('birth_state');
            $table->unsignedSmallInteger('death_year')->nullable()->after('birth_country');
            $table->unsignedTinyInteger('death_month')->nullable()->after('death_year');
            $table->unsignedTinyInteger('death_day')->nullable()->after('death_month');
            $table->string('death_city')->nullable()->after('death_day');
            $table->string('death_state')->nullable()->after('death_city');
            $table->string('death_country')->nullable()->after('death_state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'middle_name', 'last_name', 'gender', 'relationship', 'designation',
                'more_details', 'birth_year', 'birth_month', 'birth_day', 'birth_city', 'birth_state', 'birth_country',
                'death_year', 'death_month', 'death_day', 'death_city', 'death_state', 'death_country',
            ]);
        });
    }
};
