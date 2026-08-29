<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a browser receive push without an account behind it.
 *
 * `push_subscriptions.user_id` was NOT NULL with a foreign key, and the subscribe route sat
 * inside the authenticated group — so push reached only people who had signed up. On a memorial
 * site that is close to nobody: the visitor arrives from a link somebody forwarded them, reads,
 * and leaves. The one channel they could have had was the email subscription, which costs them
 * an address they may not want to give.
 *
 * A guest subscription hangs off `memorial_subscriptions` rather than standing alone, because
 * that table already answers "who wants to hear about this memorial, and about what" — the two
 * preference flags, the unsubscribe route, and the fan-out in
 * NotificationService::notifyMemorialSubscribers() are all built around it. Pointing at it
 * rather than at a memorial directly means a guest's push and a guest's email are the same
 * subscription with two channels, and turning one off cannot leave the other running.
 *
 * A signed-in user's rows keep `memorial_subscription_id` null: theirs is a device registration
 * that follows the account across every memorial, which is what it has always been.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            // Named explicitly: Laravel's convention is <table>_<column>_foreign, and dropping
            // it by column name would need the constraint to be discoverable, which it is not
            // on every driver.
            $table->dropForeign(['user_id']);
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->foreignId('memorial_subscription_id')
                ->nullable()
                ->after('user_id')
                ->constrained('memorial_subscriptions')
                // The subscription is the consent. Withdraw it and the device registration
                // goes with it, rather than lingering as an endpoint nothing can explain.
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('memorial_subscription_id');
        });

        // Rows with no user cannot survive a NOT NULL column, and they are precisely the ones
        // this migration exists to allow. Dropping them is the only honest reversal.
        \Illuminate\Support\Facades\DB::table('push_subscriptions')->whereNull('user_id')->delete();

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('push_subscriptions', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
