<?php

/**
 * Who hears about what.
 *
 * The Notification Events panel in Settings promises three audiences with different reach,
 * and the routing behind it was inverted once already — super-admins were skipped on every
 * memorial they did not personally own, so the one account watching the whole platform
 * received nothing and it read as notifications being broken outright.
 *
 * These lock the promise down, because the failure is silent: nothing errors, mail simply
 * never arrives, and nobody notices until someone asks why the bell is empty.
 */

use App\Models\Memorial;
use App\Models\MemorialSubscription;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'reseller', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/** @return array{0: User, 1: User, 2: User, 3: Memorial} */
function routingCast(): array
{
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super-admin');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $owner = User::factory()->create();
    $owner->assignRole('user');

    $memorial = Memorial::factory()->create(['user_id' => $owner->id]);

    return [$superAdmin, $admin, $owner, $memorial];
}

function notifiedUserIds(string $type): array
{
    return Notification::where('type', $type)->pluck('user_id')->all();
}

it('sends memorial events to the super-admin even when they do not own the memorial', function () {
    [$superAdmin, , , $memorial] = routingCast();

    NotificationService::sendToAdminsForMemorial($memorial, 'new_tribute', 'T', 'M');

    expect(notifiedUserIds('new_tribute'))->toContain($superAdmin->id);
});

it('does not send another owner\'s memorial events to a plain admin', function () {
    [, $admin, , $memorial] = routingCast();

    NotificationService::sendToAdminsForMemorial($memorial, 'new_tribute', 'T', 'M');

    expect(notifiedUserIds('new_tribute'))->not->toContain($admin->id);
});

it('sends memorial events to a plain admin for a memorial they own themselves', function () {
    [, $admin] = routingCast();
    $theirs = Memorial::factory()->create(['user_id' => $admin->id]);

    NotificationService::sendToAdminsForMemorial($theirs, 'new_tribute', 'T', 'M');

    expect(notifiedUserIds('new_tribute'))->toContain($admin->id);
});

it('sends platform-wide events to both the super-admin and plain admins', function () {
    [$superAdmin, $admin, $owner] = routingCast();

    NotificationService::notifyNewUserSignup($owner);

    $notified = notifiedUserIds('new_user_signup');

    expect($notified)->toContain($superAdmin->id)
        ->and($notified)->toContain($admin->id)
        // The person who signed up is not an audience for their own signup.
        ->and($notified)->not->toContain($owner->id);
});

it('tells the memorial owner when their own memorial changes status', function () {
    [, , $owner, $memorial] = routingCast();

    NotificationService::notifyMemorialStatusChange($memorial, 'active');

    expect(notifiedUserIds('memorial_status_change'))->toBe([$owner->id]);
});

it('emails a signed-out visitor who subscribed, and gives them no in-app row to read', function () {
    [, , , $memorial] = routingCast();

    MemorialSubscription::create([
        'memorial_id' => $memorial->id,
        'user_id' => null,
        'guest_name' => 'A Visitor',
        'guest_email' => 'visitor@example.test',
        'notify_tributes' => true,
        'notify_life_chapters' => true,
    ]);

    Illuminate\Support\Facades\Queue::fake();

    NotificationService::notifyMemorialSubscribers(
        $memorial,
        'notify_tributes',
        'New Tribute',
        'Someone left a flower.',
        'https://example.test/memorial',
    );

    // A visitor holds no account, so there is nowhere in the app to show them this: the
    // email is the whole delivery, and an in-app row would belong to no one.
    expect(Notification::where('type', 'memorial_subscription')->count())->toBe(0);

    Illuminate\Support\Facades\Queue::assertPushed(
        App\Jobs\SendRawEmail::class,
        fn ($job) => $job->to === 'visitor@example.test'
    );
});

it('leaves a visitor unsubscribed from the event they did not opt into', function () {
    [, , , $memorial] = routingCast();

    MemorialSubscription::create([
        'memorial_id' => $memorial->id,
        'user_id' => null,
        'guest_name' => 'A Visitor',
        'guest_email' => 'visitor@example.test',
        'notify_tributes' => true,
        'notify_life_chapters' => false,
    ]);

    Illuminate\Support\Facades\Queue::fake();

    NotificationService::notifyMemorialSubscribers(
        $memorial,
        'notify_life_chapters',
        'New Life Chapter',
        'A chapter was added.',
        'https://example.test/memorial',
    );

    Illuminate\Support\Facades\Queue::assertNotPushed(App\Jobs\SendRawEmail::class);
});

it('puts a working unsubscribe link in the visitor email that stops the next one', function () {
    [, , , $memorial] = routingCast();

    $sub = MemorialSubscription::create([
        'memorial_id' => $memorial->id,
        'user_id' => null,
        'guest_name' => 'A Visitor',
        'guest_email' => 'visitor@example.test',
        'notify_tributes' => true,
        'notify_life_chapters' => true,
    ]);

    Illuminate\Support\Facades\Queue::fake();

    NotificationService::notifyMemorialSubscribers(
        $memorial, 'notify_tributes', 'New Tribute', 'Someone left a flower.', 'https://example.test/m',
    );

    $body = null;
    Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\SendRawEmail::class, function ($job) use (&$body) {
        $body = $job->body;

        return true;
    });

    // Pull the link straight out of the message, so this asserts on what the visitor is
    // actually given rather than on a URL rebuilt to match.
    expect($body)->toContain('Unsubscribe');
    preg_match('#href="([^"]*unsubscribe[^"]*)"#', (string) $body, $m);
    expect($m[1] ?? null)->not->toBeNull();

    $link = html_entity_decode($m[1]);

    test()->get($link)->assertOk()->assertSee('unsubscribed', false);

    expect(MemorialSubscription::find($sub->id))->toBeNull();
});

it('refuses an unsubscribe link whose signature has been tampered with', function () {
    [, , , $memorial] = routingCast();

    $sub = MemorialSubscription::create([
        'memorial_id' => $memorial->id,
        'user_id' => null,
        'guest_email' => 'visitor@example.test',
        'notify_tributes' => true,
        'notify_life_chapters' => true,
    ]);

    // Somebody else's subscription id, pasted into a link signed for this one.
    $path = Illuminate\Support\Facades\URL::signedRoute(
        'memorial.unsubscribe', ['subscription' => $sub->id], absolute: false,
    );
    $tampered = str_replace('/unsubscribe/'.$sub->id, '/unsubscribe/'.($sub->id + 1), $path);

    test()->get($tampered)->assertForbidden();

    expect(MemorialSubscription::find($sub->id))->not->toBeNull();
});
