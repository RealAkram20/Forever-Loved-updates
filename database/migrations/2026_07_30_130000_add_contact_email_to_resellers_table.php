<?php

use App\Models\Reseller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a reseller's contact form lands.
 *
 * Their Contact page previously redirected to their front page, so there was nowhere for an
 * enquiry to go and no field to hold it. Backfilled from the owner's account so the page
 * works the moment it is switched on, and separable afterwards — enquiries usually belong in
 * a shared inbox rather than one person's login address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->string('contact_email')->nullable()->after('name');
        });

        Reseller::query()->with('owner')->chunkById(100, function ($resellers) {
            foreach ($resellers as $reseller) {
                if ($email = $reseller->owner?->email) {
                    $reseller->updateQuietly(['contact_email' => $email]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('resellers', function (Blueprint $table) {
            $table->dropColumn('contact_email');
        });
    }
};
