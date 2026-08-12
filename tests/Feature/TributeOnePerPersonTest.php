<?php

use App\Models\Memorial;
use App\Models\Tribute;
use App\Models\User;
use App\Support\VisitorToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('counts one tribute per person per type however many times they tap', function () {
    $visitor = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (range(1, 5) as $tap) {
        $res = $this->actingAs($visitor)
            ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'flower'])
            ->assertOk();

        // Every tap succeeds — the page needs that to keep playing its burst — but only
        // the first one is a new tribute.
        $res->assertJson(['success' => true, 'duplicate' => $tap > 1]);
    }

    expect($memorial->tributes()->where('type', 'flower')->count())->toBe(1);
});

it('still lets one person leave each different kind', function () {
    $visitor = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (Tribute::TYPES as $type) {
        $this->actingAs($visitor)
            ->postJson("/m/{$memorial->slug}/tribute", ['type' => $type])
            ->assertOk()
            ->assertJson(['duplicate' => false]);
    }

    expect($memorial->tributes()->count())->toBe(count(Tribute::TYPES));
});

it('keeps different people counting separately', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (range(1, 3) as $i) {
        $this->actingAs(User::factory()->create())
            ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'candle'])
            ->assertOk()
            ->assertJson(['duplicate' => false]);
    }

    expect($memorial->tributes()->where('type', 'candle')->count())->toBe(3);
});

/**
 * Writing is not capped this way, and must not be: a person may say as many things about
 * someone as they have to say. Only the gesture is one-per-person — that is what makes
 * "12 candles" mean twelve people.
 */
it('lets one person write as many stories as they like, marked the same way', function () {
    $visitor = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (['The first thing I said.', 'And another, a week later.'] as $words) {
        $this->actingAs($visitor)
            ->postJson("/m/{$memorial->slug}/tribute-post", [
                'content' => $words,
                'tribute_type' => 'prayer',
            ])
            ->assertOk();
    }

    expect($memorial->posts()->where('tribute_type', 'prayer')->count())->toBe(2);
});

/**
 * A guest taps and is asked for nothing.
 *
 * This used to be the worst moment on the page. The first tap made you type a name and an
 * address, quietly registered that address as an account, and keyed the tribute on it — so
 * the second tap came back 422 "an account already uses this email", and the page told a
 * mourner to go and sign in. The gesture is anonymous now, and the server tells you apart
 * from the next visitor by a cookie it issues itself.
 */
it('records a guest tap with no name, no email and no account', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);
    $usersBefore = User::count();

    $this->postJson("/m/{$memorial->slug}/tribute", ['type' => 'flower'])
        ->assertOk()
        ->assertJson(['success' => true, 'duplicate' => false])
        ->assertCookie(VisitorToken::COOKIE);

    expect($memorial->tributes()->count())->toBe(1)
        ->and(User::count())->toBe($usersBefore);
});

it('counts a guest once across repeat taps', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    // The browser the visitor tapped from. Laravel's test client does not carry a response
    // cookie into the next request, so the returning visit is spelled out.
    $browser = 'the-same-browser';

    $this->withCredentials()->withCookie(VisitorToken::COOKIE, $browser)
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'flower'])
        ->assertOk()
        ->assertJson(['duplicate' => false]);

    // Second tap: still a success, so the card keeps playing its burst — but nothing new
    // is stored and the tally does not move.
    $this->withCredentials()->withCookie(VisitorToken::COOKIE, $browser)
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'flower'])
        ->assertOk()
        ->assertJson(['success' => true, 'duplicate' => true]);

    expect($memorial->tributes()->count())->toBe(1);
});

it('still lets one guest leave each different kind', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);
    $browser = 'one-visitor';

    foreach (Tribute::TYPES as $type) {
        $this->withCredentials()->withCookie(VisitorToken::COOKIE, $browser)
            ->postJson("/m/{$memorial->slug}/tribute", ['type' => $type])
            ->assertOk()
            ->assertJson(['duplicate' => false]);
    }

    expect($memorial->tributes()->count())->toBe(count(Tribute::TYPES));
});

/**
 * The reason the key had to move off IP: a household, an office or anyone behind carrier
 * NAT shares an address, and keying on that would have swallowed a real person's candle
 * because a relative on the same sofa had already lit one.
 */
it('counts two guests on separate browsers separately', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (['her-phone', 'his-phone', 'the-kitchen-tablet'] as $browser) {
        $this->withCredentials()->withCookie(VisitorToken::COOKIE, $browser)
            ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'candle'])
            ->assertOk()
            ->assertJson(['duplicate' => false]);
    }

    expect($memorial->tributes()->where('type', 'candle')->count())->toBe(3);
});

/**
 * The gesture that used to set the trap must no longer set it. A guest lights a candle and
 * then takes the page's own offer to say a few words with it — the step that always failed,
 * because the tap had just registered the address the composer went on to reject.
 */
it('lets a guest write a story straight after tapping', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);
    $browser = 'the-mourner';

    $this->withCredentials()->withCookie(VisitorToken::COOKIE, $browser)
        ->postJson("/m/{$memorial->slug}/tribute", ['type' => 'candle'])
        ->assertOk();

    $this->withCredentials()->withCookie(VisitorToken::COOKIE, $browser)
        ->postJson("/m/{$memorial->slug}/tribute-post", [
            'content' => 'He taught me to drive on that hill.',
            'tribute_type' => 'candle',
            'guest_name' => 'Grace',
            'guest_email' => 'grace@example.com',
        ])
        ->assertOk();

    expect($memorial->posts()->where('tribute_type', 'candle')->count())->toBe(1);
});
