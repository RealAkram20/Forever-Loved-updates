<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A selectable theme: a template (markup, on disk) plus the palette it runs with.
 *
 * The split matters. `template` names a directory under `themes/`, which is code
 * and ships with a deploy; a row here is an *instance* of one — "Basic in our green", "Basic
 * as the platform ships it". That is what lets the catalogue and a reseller's own saved looks
 * be the same kind of object, and it is the only honest reading of "resellers author themes
 * too": they compose template + colours + type and name the result. They do not author
 * blades. Arbitrary markup executed inside our layout, with our session cookie in scope, is
 * not something a UI can make safe.
 *
 * `reseller_id` null means the platform catalogue — visible to everyone, editable by admin.
 * A row with a reseller_id belongs to that tenant and is visible only to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            // Null = platform catalogue. Cascade for a tenant's own: a saved look is a
            // presentation preference, meaningless once its owner is gone.
            $table->foreignId('reseller_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            // The directory under themes/. Not a foreign key to anything, because
            // the templates live in the repo — see ThemeRegistry. An unknown value here
            // renders as the base template rather than 500ing the site.
            $table->string('template');
            // Appearance keys => values, filtered against AppearanceKeys::resellerWritable()
            // before they are ever read. Null means "this theme changes markup only".
            $table->json('tokens')->nullable();
            $table->boolean('is_published')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Scoped rather than global: a reseller naming their theme 'classic' must not
            // collide with the platform's, or with another tenant's.
            $table->unique(['reseller_id', 'slug']);
            $table->index(['reseller_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
