<?php

use App\Models\Memorial;
use App\Models\MemorialSubscription;
use App\Models\PushSubscription;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Push, for the people who actually visit memorials.
 *
 * `push_subscriptions.user_id` was NOT NULL and the subscribe route sat inside the authenticated
 * group, so push reached only account holders. On a memorial site that is close to nobody: the
 * visitor arrives from a link somebody forwarded, reads, and leaves. The only channel open to
 * them cost an email address.
 *
 * What is asserted here is mostly what must NOT happen. This is somebody's grief with a
 * notification pipeline attached, and the ways to get it wrong — telling one memorial's
 * subscribers about another's, sending to someone who turned that off, keeping a consent record
 * with no way to withdraw it — are all paid for by a family rather than by us.
 */
function pushMemorial(bool $guestNotifications = true): Memorial
{
    SystemSetting::set('notifications.push_enabled', true);
    SystemSetting::set('notifications.vapid_public_key', 'test-public-key');
    SystemSetting::set('notifications.vapid_private_key', 'test-private-key');

    $owner = User::factory()->create();

    $plan = SubscriptionPlan::create([
        'name' => 'Premium',
        'slug' => 'premium-'.uniqid(),
        'price' => 0,
        'is_active' => true,
        'feature_guest_notifications' => $guestNotifications,
    ]);

    return Memorial::factory()->create([
        'user_id' => $owner->id,
        'reseller_id' => null,
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
        'subscription_plan_id' => $plan->id,
    ]);
}

function pushPayload(string $endpoint = 'https://push.example.test/abc'): array
{
    return [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => 'a-public-key', 'auth' => 'an-auth-token'],
        'contentEncoding' => 'aes128gcm',
    ];
}

it('lets a visitor with no account subscribe a browser', function () {
    $memorial = pushMemorial();

    $this->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload())
        ->assertOk()
        ->assertJson(['success' => true, 'scope' => 'memorial']);

    $push = PushSubscription::firstOrFail();

    expect($push->user_id)->toBeNull()
        ->and($push->memorialSubscription->memorial_id)->toBe($memorial->id)
        // No name, no address. The whole point is that it costs the visitor nothing to give.
        ->and($push->memorialSubscription->guest_email)->toBeNull();
});

it('does not create a second subscription when the same browser asks twice', function () {
    // A reload, or a visitor tapping again on their next visit. Two rows would mean two pushes
    // for one story, to one phone.
    $memorial = pushMemorial();

    $this->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload())->assertOk();
    $this->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload())->assertOk();

    expect(PushSubscription::count())->toBe(1)
        ->and(MemorialSubscription::count())->toBe(1);
});

it('keeps a second browser on the same memorial as its own registration', function () {
    // A phone and a laptop are two endpoints and both should ring.
    $memorial = pushMemorial();

    $this->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload('https://push.example.test/phone'))->assertOk();
    $this->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload('https://push.example.test/laptop'))->assertOk();

    expect(PushSubscription::count())->toBe(2);
});

it('keeps a signed-in person registration on their account, not on one memorial', function () {
    // Theirs is a device that follows them everywhere, which is what it has always been —
    // the guest path must not quietly narrow it to whichever memorial they happened to open.
    $memorial = pushMemorial();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload())
        ->assertOk()
        ->assertJson(['scope' => 'account']);

    $push = PushSubscription::firstOrFail();

    expect($push->user_id)->toBe($user->id)
        ->and($push->memorial_subscription_id)->toBeNull()
        ->and(MemorialSubscription::count())->toBe(0);
});

it('refuses on a plan without guest notifications', function () {
    // The same gate the email subscription and the sidebar card already sit behind.
    $memorial = pushMemorial(guestNotifications: false);

    $this->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload())->assertStatus(422);

    expect(PushSubscription::count())->toBe(0);
});

it('takes the registration and the consent away together', function () {
    // A push-only guest has no address, so a MemorialSubscription left behind would be a
    // consent record for a channel nobody can be reached on — and one they could never find
    // their way back to in order to withdraw.
    $memorial = pushMemorial();

    $this->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload())->assertOk();
    $this->postJson('/m/'.$memorial->slug.'/push/unsubscribe', ['endpoint' => 'https://push.example.test/abc'])->assertOk();

    expect(PushSubscription::count())->toBe(0)
        ->and(MemorialSubscription::count())->toBe(0);
});

it('leaves an email subscriber subscribed when their push goes', function () {
    // Somebody who gave an address *and* turned push on has two channels. Dropping one must
    // not silently end the other.
    $memorial = pushMemorial();

    $subscription = MemorialSubscription::create([
        'memorial_id' => $memorial->id,
        'guest_email' => 'someone@example.test',
        'guest_name' => 'Someone',
    ]);
    PushSubscription::create([
        'memorial_subscription_id' => $subscription->id,
        'endpoint' => 'https://push.example.test/abc',
        'p256dh_key' => 'k',
        'auth_token' => 't',
        'content_encoding' => 'aes128gcm',
    ]);

    $this->postJson('/m/'.$memorial->slug.'/push/unsubscribe', ['endpoint' => 'https://push.example.test/abc'])->assertOk();

    expect(PushSubscription::count())->toBe(0)
        ->and(MemorialSubscription::whereKey($subscription->id)->exists())->toBeTrue();
});

it('never lets one memorial unsubscribe another memorial browser', function () {
    // Same endpoint, two memorials — the same phone following two people. Unsubscribing from
    // one must leave the other alone.
    $one = pushMemorial();
    $two = pushMemorial();

    $this->postJson('/m/'.$one->slug.'/push/subscribe', pushPayload())->assertOk();
    $this->postJson('/m/'.$two->slug.'/push/subscribe', pushPayload())->assertOk();

    $this->postJson('/m/'.$one->slug.'/push/unsubscribe', ['endpoint' => 'https://push.example.test/abc'])->assertOk();

    $left = PushSubscription::with('memorialSubscription')->get();

    expect($left)->toHaveCount(1)
        ->and($left->first()->memorialSubscription->memorial_id)->toBe($two->id);
});

/*
|--------------------------------------------------------------------------
| Delivery — who gets addressed
|--------------------------------------------------------------------------
|
| Asserted on the selection rather than through the transport. Sending needs real VAPID keys
| and GMP or BCMath; who is selected needs neither, and it is the half that matters.
*/

it('addresses only the browsers subscribed to that memorial', function () {
    // The failure that would matter most: one family's news arriving on the phone of someone
    // who subscribed to a different person entirely.
    $mine = pushMemorial();
    $theirs = pushMemorial();

    $this->postJson('/m/'.$mine->slug.'/push/subscribe', pushPayload('https://push.example.test/mine'))->assertOk();
    $this->postJson('/m/'.$theirs->slug.'/push/subscribe', pushPayload('https://push.example.test/theirs'))->assertOk();

    expect(NotificationService::guestPushSubscriptionsFor($mine, 'notify_tributes')->pluck('endpoint')->all())
        ->toBe(['https://push.example.test/mine']);
});

it('honours the preference the subscription carries', function () {
    // The flags gate the email already. Push has to answer to the same ones, or turning
    // tributes off would silence the mail and leave the phone buzzing.
    $memorial = pushMemorial();

    $this->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload())->assertOk();

    expect(NotificationService::guestPushSubscriptionsFor($memorial, 'notify_tributes'))->toHaveCount(1);

    MemorialSubscription::firstOrFail()->update(['notify_tributes' => false]);

    expect(NotificationService::guestPushSubscriptionsFor($memorial, 'notify_tributes'))->toBeEmpty()
        // The other channel is untouched: one flag off is not all of them off.
        ->and(NotificationService::guestPushSubscriptionsFor($memorial, 'notify_life_chapters'))->toHaveCount(1);
});

it('addresses nobody when push is switched off platform-wide', function () {
    $memorial = pushMemorial();
    $this->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload())->assertOk();

    expect(NotificationService::guestPushSubscriptionsFor($memorial, 'notify_tributes'))->toHaveCount(1);

    SystemSetting::set('notifications.push_enabled', false);

    expect(NotificationService::guestPushSubscriptionsFor($memorial, 'notify_tributes'))->toBeEmpty();
});

it('never addresses a signed-in person device through the guest path', function () {
    // Members are reached by their Notification, which dispatches push off its own back. If
    // the guest query also picked them up they would get two of everything.
    $memorial = pushMemorial();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/m/'.$memorial->slug.'/push/subscribe', pushPayload())->assertOk();

    expect(NotificationService::guestPushSubscriptionsFor($memorial, 'notify_tributes'))->toBeEmpty();
});
