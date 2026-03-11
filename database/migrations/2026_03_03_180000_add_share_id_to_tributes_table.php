<?php

use App\Models\Tribute;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tributes', function (Blueprint $table) {
            $table->string('share_id', 10)->nullable()->unique()->after('id');
        });

        foreach (Tribute::all() as $tribute) {
            $tribute->update(['share_id' => Tribute::generateUniqueShareId()]);
        }

        Schema::table('tributes', function (Blueprint $table) {
            $table->string('share_id', 10)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tributes', function (Blueprint $table) {
            $table->dropColumn('share_id');
        });
    }
};
