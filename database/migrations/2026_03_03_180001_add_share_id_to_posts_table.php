<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('share_id', 10)->nullable()->unique()->after('id');
        });

        foreach (Post::all() as $post) {
            $post->update(['share_id' => Post::generateUniqueShareId()]);
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->string('share_id', 10)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('share_id');
        });
    }
};
