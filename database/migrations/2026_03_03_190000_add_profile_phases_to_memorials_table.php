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
            $table->string('short_description')->nullable()->after('designation');
            $table->string('nationality')->nullable()->after('short_description');
            $table->string('primary_profession')->nullable()->after('nationality');
            $table->string('notable_title')->nullable()->after('primary_profession');
            $table->text('major_achievements')->nullable()->after('more_details');
            $table->text('known_for')->nullable()->after('major_achievements');
            $table->unsignedSmallInteger('active_year_start')->nullable()->after('known_for');
            $table->unsignedSmallInteger('active_year_end')->nullable()->after('active_year_start');
            $table->string('cause_of_death')->nullable()->after('death_country');
            $table->boolean('cause_of_death_private')->default(false)->after('cause_of_death');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memorials', function (Blueprint $table) {
            $table->dropColumn([
                'short_description',
                'nationality',
                'primary_profession',
                'notable_title',
                'major_achievements',
                'known_for',
                'active_year_start',
                'active_year_end',
                'cause_of_death',
                'cause_of_death_private',
            ]);
        });
    }
};
