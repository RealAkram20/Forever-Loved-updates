<?php

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\WebPush;

uses(RefreshDatabase::class);

/**
 * The argument that killed every push notification.
 *
 * WebPush used to be constructed `(auth, defaultOptions, timeout, clientOptions)` and built its
 * own HTTP client from the last two. Since v11 the third argument is a PSR-18 client and the
 * other two are gone — so the old call passed `30` where an object was expected and every push
 * job died before sending anything:
 *
 *   TypeError: WebPush::__construct(): Argument #3 ($client) must be of type
 *   ?Psr\Http\Client\ClientInterface, int given
 *
 * Four failed jobs on the dashboard and no notification delivered. It arrived without a code
 * change on our side, because the requirement was `"minishlink/web-push": "*"` — a wildcard that
 * lets a major version in on any rebuild.
 *
 * These assert the shape of the call rather than mocking it, because the shape was the bug.
 */
it('hands WebPush an HTTP client, not a number', function () {
    // The reflection is the point: this is exactly the check that failed in production, made
    // against the library actually installed rather than against a remembered signature.
    $third = (new ReflectionMethod(WebPush::class, '__construct'))->getParameters()[2];

    expect($third->getName())->toBe('client')
        ->and((string) $third->getType())->toContain(Psr\Http\Client\ClientInterface::class);

    $client = (new ReflectionMethod(NotificationService::class, 'webPushClient'))
        ->invoke(null);

    expect($client)->toBeInstanceOf(Psr\Http\Client\ClientInterface::class);
});

it('keeps the timeout and the TLS setting that used to be arguments three and four', function () {
    // Both were real settings, not defaults: the timeout bounds a push endpoint that hangs, and
    // the CA bundle is why a local machine with no bundle can still send. Moving them onto the
    // client is what stops them being silently dropped.
    $client = (new ReflectionMethod(NotificationService::class, 'webPushClient'))->invoke(null);

    $config = (new ReflectionMethod(GuzzleHttp\Client::class, 'getConfig'))->isPublic()
        ? $client->getConfig()
        : (fn () => $this->config)->call($client);

    expect($config['timeout'] ?? null)->toBe(30)
        // Guzzle always carries a `verify` key, defaulting to true, so what matters is that it
        // is not the local waiver: a push endpoint we cannot verify must fail here, loudly.
        ->and($config['verify'] ?? true)->not->toBeFalse();
});

it('still waives TLS verification on a developer machine', function () {
    // The waiver is the reason getWebPushClientOptions() exists — a local machine with no CA
    // bundle could otherwise never test a push at all. It has to survive the move onto the
    // client, and has to stay scoped to the environments it was written for.
    app()->detectEnvironment(fn () => 'local');

    $client = (new ReflectionMethod(NotificationService::class, 'webPushClient'))->invoke(null);
    $config = (fn () => $this->config)->call($client);

    expect($config['verify'] ?? null)->toBeFalse()
        ->and($config['timeout'] ?? null)->toBe(30);
});

it('constructs the sender without throwing', function () {
    // The end-to-end shape. No request is made — an empty subscription collection returns before
    // any network call — but the constructor runs, which is where it died.
    SystemSetting::set('notifications.push_enabled', true);

    $user = User::factory()->create();

    expect(NotificationService::sendPushToSubscriptions(
        collect(),
        'Title',
        'Body',
        'https://example.test/m/someone',
        'tag-'.$user->id,
    ))->toBe([]);
});
