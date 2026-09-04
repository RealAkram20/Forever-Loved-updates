<?php

use App\Rules\HumanName;
use Illuminate\Support\Facades\Validator;

/**
 * The second sweep, after the registration relay was closed on 2026-09-04.
 *
 * Closing `register` did not close the attack — it closed one door into it. The payload has to
 * ride in a name, and the site had several other name fields with the same `max:255` and no
 * link check:
 *
 *   - `guest_name` on memorial subscribe. NotificationService:783 reads it into every
 *     notification mail that memorial sends, and the same request supplies `guest_email`.
 *     That is the identical relay with a different door.
 *   - `guest_name` on comments, tributes and tribute posts, printed under every entry on a
 *     public memorial page.
 *   - `first_name` / `middle_name` / `last_name` on a memorial, which are the <title> and
 *     <h1> of a public, indexable page.
 *
 * This asserts the rule reaches the fields, not that the rule works — NameSpamRelayTest owns
 * that. What would regress here is somebody adding a name field and forgetting.
 */
$payload = 'Your account has been dormant for 364 days. To prevent removal and retrieve your funds, please access your account and request a withdrawal within 24 hours. For assistance, join graph.org/vJJgPbfz8o-09-04?OwDpHA';

it('rejects the payload in every user-supplied name field', function () use ($payload) {
    // Each of these is a real field name from a controller that now uses the rule.
    foreach (['guest_name', 'first_name', 'middle_name', 'last_name', 'name'] as $field) {
        expect(Validator::make([$field => $payload], [$field => new HumanName])->fails())
            ->toBeTrue("{$field} accepted the phishing payload");
    }
});

it('keeps every name field usable by an ordinary family', function () {
    foreach (['guest_name', 'first_name', 'middle_name', 'last_name', 'name'] as $field) {
        expect(Validator::make([$field => 'Nakato Nabirye'], [$field => new HumanName])->fails())
            ->toBeFalse("{$field} rejected an ordinary name");
    }
});

/**
 * Every controller that takes a name now imports the rule and uses it. Written as a source
 * check rather than a request test because the point is coverage — a name field added later
 * with `max:255` is the regression, and no functional test would notice it.
 */
it('leaves no user-supplied name field on max:255 in the guest-reachable controllers', function () {
    $controllers = [
        'app/Http/Controllers/Auth/RegisteredUserController.php',
        'app/Http/Controllers/MemorialSignupController.php',
        'app/Http/Controllers/ContactController.php',
        'app/Http/Controllers/MemorialApiController.php',
        'app/Http/Controllers/MemorialMediaController.php',
        'app/Http/Controllers/MemorialController.php',
    ];

    foreach ($controllers as $path) {
        $source = file_get_contents(base_path($path));

        foreach (['guest_name', 'first_name', 'middle_name', 'last_name'] as $field) {
            expect($source)->not->toContain("'{$field}' => ['required', 'string', 'max:255']");
            expect($source)->not->toContain("'{$field}' => ['nullable', 'string', 'max:255']");
        }
    }
});

/**
 * The memorial signup wizard had no rate limit on any step, including the one that creates an
 * account and mails it — while `/register` beside it sat at 6/min.
 */
it('rate limits every step of the memorial signup wizard', function () {
    $expected = [
        'memorial.create.storeStep1' => 'throttle:20,1',
        'memorial.create.storeStep2Register' => 'throttle:6,1',
        'memorial.create.storeStep2Login' => 'throttle:10,1',
        'memorial.create.storeStep3' => 'throttle:20,1',
        'memorial.create.preparePaidCheckout' => 'throttle:12,1',
        'password.store' => 'throttle:6,1',
    ];

    foreach ($expected as $name => $throttle) {
        $route = app('router')->getRoutes()->getByName($name);

        // Two separate expectations on purpose: Pest carries the `not` modifier across
        // `->and()`, which silently inverted the throttle assertion and reported the
        // opposite of the truth.
        expect($route)->not->toBeNull("route {$name} is missing");
        expect($route->gatherMiddleware())->toContain($throttle);
    }
});

it('leaves no unauthenticated write route without either a throttle or a policy check', function () {
    // A guard against the next route added in a hurry. The exemptions are named individually
    // rather than pattern-matched, so adding one is a deliberate act that shows up in review.
    $exempt = [
        'payment.ipn',                  // provider callback; throttling it drops real payments
        'storage.local.upload',         // local-disk dev route, 404 in production
        'memorial.api.track-share',     // a counter increment, no content and no mail
        'login',                        // LoginRequest has its own RateLimiter
    ];

    $unguarded = [];

    foreach (app('router')->getRoutes() as $route) {
        if (! array_intersect(['POST', 'PUT', 'PATCH'], $route->methods())) {
            continue;
        }

        $mw = implode(',', $route->gatherMiddleware());
        $name = $route->getName() ?? $route->uri();

        if (str_contains($mw, 'auth') || str_contains($mw, 'throttle')) {
            continue;
        }

        if (in_array($name, $exempt, true)) {
            continue;
        }

        // The memorial editor routes guard with canEdit() in the controller and answer 403.
        if (str_starts_with($route->uri(), 'm/{slug}')) {
            continue;
        }

        if (str_starts_with($route->uri(), 'install') || str_contains($route->uri(), 'reseller/') || str_starts_with($route->uri(), 'admin')) {
            continue;
        }

        $unguarded[] = $name.'  ['.$route->uri().']';
    }

    expect($unguarded)->toBe([], "unguarded write routes:\n  ".implode("\n  ", $unguarded));
});
