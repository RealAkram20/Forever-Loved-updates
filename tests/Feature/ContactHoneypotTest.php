<?php

use App\Models\SystemSetting;
use App\Support\Honeypot;
use App\Support\TrustedProxies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

/**
 * The honeypot on the public contact endpoint.
 *
 * The thing worth asserting is not "spam is blocked" — it is that a caught bot cannot tell it
 * was caught. A honeypot that answers differently is a honeypot that gets fixed by whoever
 * wrote the bot.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // The controller bails out before dispatching unless SMTP looks configured, so a test that
    // did not set these would pass for the wrong reason: no mail sent because no mail is ever
    // sent here, honeypot or not.
    SystemSetting::set('smtp.enabled', true);
    SystemSetting::set('smtp.host', 'smtp.example.test');
    SystemSetting::set('smtp.from_address', 'hello@example.test');

    Queue::fake();
});

$valid = [
    'name' => 'Grace Namutebi',
    'email' => 'grace@example.test',
    'subject' => 'Arranging a service',
    'message' => 'Please could someone call me this afternoon.',
];

it('sends the message when the honeypot is left alone', function () use ($valid) {
    $this->post(route('contact.send'), $valid)
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(\App\Jobs\SendContactEmail::class);
});

it('sends nothing when the honeypot is filled', function () use ($valid) {
    $this->post(route('contact.send'), $valid + [Honeypot::FIELD => 'http://spam.example'])
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertNothingPushed();
});

it('answers a caught bot exactly as it answers a person', function () use ($valid) {
    $human = $this->post(route('contact.send'), $valid);
    $bot = $this->post(route('contact.send'), $valid + [Honeypot::FIELD => 'x']);

    expect($bot->getStatusCode())->toBe($human->getStatusCode())
        ->and(session()->get('success'))->not->toBeNull();

    // The tell would be a validation error bag, or a different flash key. Neither may differ.
    $bot->assertSessionHasNoErrors();
});

it('does not validate a caught bot, so it learns no field names', function () {
    // Garbage in every field *and* the honeypot filled. A real submission this malformed would
    // come back 422 with a list of what was wrong; a caught one must not.
    $this->post(route('contact.send'), [Honeypot::FIELD => 'x'])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    Queue::assertNothingPushed();
});

it('treats a missing honeypot field as a person, not a bot', function () use ($valid) {
    // A cached page from before this shipped posts without the key at all. Absent must mean
    // "no opinion" — reading it as spam would silently swallow real messages.
    expect(Honeypot::tripped(Request::create('/', 'POST', $valid)))->toBeFalse();

    $this->post(route('contact.send'), $valid)->assertSessionHas('success');
    Queue::assertPushed(\App\Jobs\SendContactEmail::class);
});

it('treats a present but empty honeypot as a person', function () use ($valid) {
    expect(Honeypot::tripped(Request::create('/', 'POST', $valid + [Honeypot::FIELD => ''])))
        ->toBeFalse();
});

it('renders the honeypot into the contact form so the check has something to catch', function () {
    // Guards the failure this whole change exists because of: the form and the controller
    // disagreeing about the field, which is what made the old comment describe a honeypot
    // that was never there.
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('name="'.Honeypot::FIELD.'"', false);
});

/**
 * Trusted proxies.
 *
 * `trustProxies(at: '*')` trusted the client's own X-Forwarded-For, and Request::ip() is the
 * key every `throttle` on this app's public routes uses.
 */
it('does not trust every proxy by default', function () {
    expect(TrustedProxies::list())->not->toBe('*')->toBeArray();
});

it('trusts the private ranges a container-networked proxy reaches php from', function () {
    expect(TrustedProxies::list())
        ->toContain('10.0.0.0/8')
        ->toContain('172.16.0.0/12')
        ->toContain('192.168.0.0/16');
});

it('keeps one env var as the way back to the old behaviour', function () {
    putenv('TRUSTED_PROXIES=*');
    expect(TrustedProxies::list())->toBe('*');

    putenv('TRUSTED_PROXIES=203.0.113.7,198.51.100.0/24');
    expect(TrustedProxies::list())->toBe(['203.0.113.7', '198.51.100.0/24']);

    putenv('TRUSTED_PROXIES');
});
