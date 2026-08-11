<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Stamps users.last_login_at on every successful authentication.
 *
 * Hung off Laravel's Login event rather than written into the controllers, because there
 * are six places that authenticate someone — password, Google, passwordless code,
 * registration, memorial signup, and reseller impersonation — and a seventh will be added
 * eventually. An event listener cannot be forgotten by that seventh one.
 */
class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // Written without touching updated_at: a sign-in is not a change to the account,
        // and letting it bump updated_at would corrupt every "accounts modified this
        // period" figure the user reports produce.
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
    }
}
