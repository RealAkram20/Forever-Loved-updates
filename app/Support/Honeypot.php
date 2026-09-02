<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The honeypot field, and the one place that decides whether a submission tripped it.
 *
 * The field name lives here rather than in each form or each controller, because a honeypot
 * whose name is written down twice is a honeypot that stops working the first time somebody
 * changes one of them. `resources/views/partials/honeypot.blade.php` renders it; this class
 * reads it; nothing else should know the string.
 *
 * **What this is not.** It stops bots that fill every input they find. It does nothing about a
 * targeted attacker, who will read the page once and skip the field forever. The throttles on
 * the routes are the real limit on abuse; this only keeps the drive-by noise out of a funeral
 * home's inbox.
 */
class Honeypot
{
    /**
     * The field name. Deliberately plausible: a bot fills it because it looks worth filling.
     */
    public const FIELD = 'website';

    /**
     * Did this request trip the trap?
     *
     * Only a *filled* field counts. A missing key is not a trip — an older cached page, a form
     * this partial has not reached yet, or a legitimate client that strips empty inputs would
     * all arrive without it, and treating that as spam would silently swallow real messages
     * from real people. Absent means "no opinion"; present-and-filled means "bot".
     */
    public static function tripped(Request $request): bool
    {
        return filled($request->input(self::FIELD));
    }

    /**
     * Record the catch, without the payload.
     *
     * At `info`, not `warning`: this is expected background noise and pages nobody at 3am. The
     * message body is deliberately not logged — spam is still someone's submitted content, and
     * a log full of it is a liability rather than an asset. The IP is enough to spot a pattern.
     */
    public static function log(Request $request, string $where): void
    {
        Log::info('Honeypot tripped', [
            'where' => $where,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 200),
        ]);
    }
}
