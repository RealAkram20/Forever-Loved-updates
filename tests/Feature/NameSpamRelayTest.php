<?php

use App\Models\User;
use App\Rules\HumanName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

/**
 * The 2026-09-04 phishing relay.
 *
 * Registrations arrived with a stranger's real email address and the entire phishing message in
 * the name field. The account was disposable; the payload was the verification mail our server
 * then sent to the victim, from our domain, opening "Hello <name>".
 *
 * The payloads below are copied from the live abuse rather than invented, so this test fails
 * the day somebody loosens the rule back to `max:255`.
 */
uses(RefreshDatabase::class);

$attack = [
    'Your account has been inactive for 364 days. To avoid deletion and claim your balance, please sign in and request a withdrawal within 24 hours. For support, join graph.org/UXoRKiiyhc-09-04?auK64r',
    'Your account has been dormant for 364 days. To prevent removal and retrieve your funds, please access your account and request a withdrawal within 24 hours. For assistance, join graph.org/vJJgPbfz8o-09-04?OwDpHA',
];

it('refuses the exact payloads the attack used', function () use ($attack) {
    foreach ($attack as $payload) {
        $v = Validator::make(['name' => $payload], ['name' => new HumanName]);
        expect($v->fails())->toBeTrue("this payload was accepted: {$payload}");
    }
});

it('refuses a bare domain with no scheme, which is what the attack actually sent', function () {
    // The tell is `graph.org/xxxx` — no http, no www. A naive "starts with http" check passes
    // it straight through, which is why the rule looks for a dotted host followed by / or ?.
    expect(Validator::make(['name' => 'graph.org/UXoRKiiyhc'], ['name' => new HumanName])->fails())
        ->toBeTrue();
});

it('refuses schemes, www, markup and line breaks', function () {
    foreach ([
        'Claim now https://evil.example',
        'Visit www.evil.example today',
        "Grace\r\nBcc: victim@example.test",
        'Grace <b>Namutebi</b>',
    ] as $payload) {
        expect(Validator::make(['name' => $payload], ['name' => new HumanName])->fails())
            ->toBeTrue("accepted: {$payload}");
    }
});

it('still accepts names that real people actually have', function () {
    // The rule is worthless if it turns away the families it is meant to protect. Apostrophes,
    // hyphens, particles, accents, non-Latin scripts, titles.
    foreach ([
        'Grace Namutebi',
        "Ngũgĩ wa Thiong'o",
        'Sr. José María Ruiz-Tagle',
        "O'Brien",
        'Anne-Marie du Pré',
        '李小龍',
        'Björk Guðmundsdóttir',
        'Nakato Nabirye Ssebugwawo',
    ] as $name) {
        expect(Validator::make(['name' => $name], ['name' => new HumanName])->fails())
            ->toBeFalse("wrongly rejected a real name: {$name}");
    }
});

it('rejects a name long enough to be a message', function () {
    expect(Validator::make(['name' => str_repeat('a', 81)], ['name' => new HumanName])->fails())
        ->toBeTrue();

    expect(Validator::make(['name' => str_repeat('a', 80)], ['name' => new HumanName])->fails())
        ->toBeFalse();
});

it('creates no account when registration carries the payload', function () use ($attack) {
    // The whole point: no user row means no verification mail, which means we do not send the
    // phishing. Asserting the user count is what actually matters here, not the status code.
    $before = User::count();

    $this->post(route('register'), [
        'name' => $attack[0],
        'email' => 'victim@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors('name');

    expect(User::count())->toBe($before)
        ->and(User::where('email', 'victim@example.test')->exists())->toBeFalse();
});

it('still lets an ordinary person register', function () {
    $this->post(route('register'), [
        'name' => 'Grace Namutebi',
        'email' => 'grace@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'grace@example.test')->exists())->toBeTrue();
});
