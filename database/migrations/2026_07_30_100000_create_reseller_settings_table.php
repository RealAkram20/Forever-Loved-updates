<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-reseller overrides for the appearance settings the platform admin controls globally.
 *
 * Deliberately shaped exactly like `system_settings` (key / value / type) and using the same
 * key names — 'branding.primary_color', 'appearance.font_body', … — so App\Support\ThemeSetting
 * can resolve reseller-then-platform without a translation layer, and BrandingHelper /
 * AppearanceHelper keep generating CSS from one vocabulary rather than two.
 *
 * A reseller previously had exactly one themeable column (primary_color), so their
 * white-labeled pages rendered their logo and brand hue over the platform's buttons,
 * backgrounds, fonts and dark theme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_settings', function (Blueprint $table) {
            $table->id();
            // Cascade: these are presentation preferences, meaningless without their tenant,
            // and unlike reseller_payments there is no history worth preserving after delete.
            $table->foreignId('reseller_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            // Nullable and text, matching system_settings: an empty value is meaningful here
            // ("use the theme default"), which is why absence-vs-empty is what decides
            // whether the platform value is inherited.
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();

            $table->unique(['reseller_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_settings');
    }
};
