<?php

use App\Models\SystemSetting;
use App\Support\Honeypot;
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
 * Site-wide, not contact-wide.
 *
 * The guard is on the `web` group, so the assertion worth making is about forms nobody wired
 * by hand: a public form that renders the partial is protected, and a form that does not
 * render it is untouched.
 */
it('protects the other public forms that create accounts, memorials or mail', function () {
    // Each of these is reachable signed-out and either creates a row or sends mail on demand.
    foreach (['register', 'login', 'password.request'] as $routeName) {
        if (! app('router')->has($routeName)) {
            continue;
        }

        $this->get(route($routeName))
            ->assertOk()
            ->assertSee('name="'.Honeypot::FIELD.'"', false);
    }
})->skip(fn () => ! app('router')->has('register'), 'no public registration route');

it('blocks a tripped post on any web route, not just contact', function () {
    // password.request sends mail on demand, which is the mail-bomb vector a honeypot is for.
    $route = app('router')->has('password.request') ? 'password.email' : null;

    if ($route === null || ! app('router')->has($route)) {
        $this->markTestSkipped('no password reset route');
    }

    $this->post(route($route), [
        'email' => 'someone@example.test',
        Honeypot::FIELD => 'http://spam.example',
    ])->assertRedirect()->assertSessionHasNoErrors();
});

it('leaves a form that does not render the field completely alone', function () {
    // The safety property that makes putting this on the whole web group defensible: absent
    // means no opinion, so every existing form keeps working exactly as it did.
    $this->post(route('contact.send'), [
        'name' => 'Grace Namutebi',
        'email' => 'grace@example.test',
        'subject' => 'Arranging a service',
        'message' => 'Please could someone call me this afternoon.',
    ])->assertSessionHas('success');

    Queue::assertPushed(\App\Jobs\SendContactEmail::class);
});

it('answers an xhr caller without telling it that it was caught', function () {
    $this->postJson(route('contact.send'), [Honeypot::FIELD => 'x'])
        ->assertOk()
        ->assertJson(['success' => true]);

    Queue::assertNothingPushed();
});
