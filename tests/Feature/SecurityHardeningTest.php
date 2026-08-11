<?php

use App\Helpers\HtmlHelper;
use App\Models\LoginCode;
use App\Models\Media;
use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\User;
use App\Services\PesapalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Regression guards for the security audit. Every case here reproduces something that was
 * genuinely exploitable on this codebase, not a hypothetical — so a failure means the hole
 * is open again, not that a style rule moved.
 */

// Memorial::canBeEditedBy() asks hasRole(['admin', 'super-admin']) on nearly every path
// through these endpoints, and Spatie throws rather than returning false when the role has
// never been defined. A real install seeds them; the test database has to as well.
beforeEach(function () {
    foreach (['super-admin', 'admin', 'reseller', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

// ─── Stored XSS ─────────────────────────────────────────────────────────────

it('strips event handlers and javascript URLs from rich text', function (string $payload, string $mustNotContain) {
    expect(HtmlHelper::sanitize($payload))->not->toContain($mustNotContain);
})->with([
    'img onerror' => ['<img src=x onerror=alert(1)>', 'onerror'],
    'div onmouseover' => ['<div onmouseover="alert(1)">x</div>', 'onmouseover'],
    'javascript href' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
    'obfuscated scheme' => ["<a href=\"java\tscript:alert(1)\">x</a>", 'script:'],
    'entity-encoded scheme' => ['<a href="&#106;avascript:alert(1)">x</a>', 'javascript'],
    'script tag' => ['<script>alert(1)</script>', 'alert'],
    'svg payload' => ['<svg><script>alert(1)</script></svg>', 'alert'],
    'inline style' => ['<p style="position:fixed">x</p>', 'style='],
    // Valid HTML needs no space after a quoted attribute, which is what defeated the old
    // `\son\w+=` regex in WidgetHtmlSanitizer.
    'no-space handler' => ['<a href="/x"onmouseover="alert(1)">x</a>', 'onmouseover'],
]);

it('keeps the formatting a tribute legitimately uses', function () {
    $clean = HtmlHelper::sanitize('<p class="ql-align-center">Béatrice — <strong>bold</strong> &amp; <em>italic</em></p>');

    expect($clean)
        ->toContain('<strong>bold</strong>')
        ->toContain('<em>italic</em>')
        ->toContain('ql-align-center')
        ->toContain('Béatrice');
});

it('neutralises rows stored before content was sanitised on write', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    // Written straight to the table, the way a row from before the fix looks. The Life feed
    // and tribute list assign these to innerHTML, so cleaning only on write would leave
    // every historic row live.
    $payload = '<img src=x onerror=alert(1)><a href="javascript:alert(2)">x</a>';

    // share_id is normally filled by a model creating hook, which a raw insert skips.
    DB::table('posts')->insert([
        'share_id' => 'legacyp',
        'memorial_id' => $memorial->id,
        'type' => 'text',
        'content' => $payload,
        'is_published' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('tributes')->insert([
        'share_id' => 'legacyt',
        'memorial_id' => $memorial->id,
        'type' => 'note',
        'message' => $payload,
        'is_approved' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $posts = $this->getJson("/m/{$memorial->slug}/posts")->assertOk()->json('posts');
    $tributes = $this->getJson("/m/{$memorial->slug}/tributes")->assertOk()->json('tributes');

    expect($posts[0]['content'])->not->toContain('onerror')
        ->and($posts[0]['content'])->not->toContain('javascript:')
        ->and($tributes[0]['message'])->not->toContain('onerror')
        ->and($tributes[0]['message'])->not->toContain('javascript:');
});

// ─── Privilege escalation ───────────────────────────────────────────────────

function makeAdmin(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('stops an admin creating a super-admin', function () {
    $this->actingAs(makeAdmin())
        ->post('/users', [
            'name' => 'Backdoor',
            'email' => 'backdoor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'super-admin',
        ])
        ->assertForbidden();

    expect(User::where('email', 'backdoor@example.com')->exists())->toBeFalse();
});

it('stops an admin promoting themselves to super-admin', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->put("/settings/permissions/users/{$admin->id}/role", ['role' => 'super-admin'])
        ->assertForbidden();

    expect($admin->fresh()->hasRole('super-admin'))->toBeFalse();
});

it('stops an admin resetting a super-admin password', function () {
    $target = makeAdmin('super-admin');

    $this->actingAs(makeAdmin())
        ->put("/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'password' => 'takeover123',
            'password_confirmation' => 'takeover123',
            'role' => 'admin',
        ])
        ->assertForbidden();

    expect($target->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('still lets a super-admin assign the super-admin role', function () {
    $this->actingAs(makeAdmin('super-admin'))
        ->post('/users', [
            'name' => 'Second Owner',
            'email' => 'owner2@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'super-admin',
        ])
        ->assertRedirect();

    expect(User::where('email', 'owner2@example.com')->first()?->hasRole('super-admin'))->toBeTrue();
});

// ─── Private memorial content ───────────────────────────────────────────────

it('does not serve a private memorial through the JSON API', function (string $endpoint) {
    $memorial = Memorial::factory()->private()->create();

    $this->getJson("/m/{$memorial->slug}/{$endpoint}")->assertNotFound();
})->with(['posts', 'tributes', 'chapters', 'stats']);

it('still serves a private memorial to its own owner', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->private()->forUser($owner)->create();

    $this->actingAs($owner)->getJson("/m/{$memorial->slug}/posts")->assertOk();
});

// ─── Identity spoofing ──────────────────────────────────────────────────────

it('refuses a guest tribute claiming a registered address', function () {
    $member = User::factory()->create(['email' => 'member@example.com']);
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->postJson("/m/{$memorial->slug}/tribute", [
        'type' => 'prayer',
        'message' => 'Signed by someone else',
        'guest_name' => 'Impostor',
        'guest_email' => 'member@example.com',
    ])
        ->assertStatus(422)
        ->assertJson(['requires_login' => true]);

    expect($memorial->tributes()->where('user_id', $member->id)->exists())->toBeFalse();
});

it('accepts every offered tribute type and rejects retired ones', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    foreach (\App\Models\Tribute::TYPES as $i => $type) {
        $this->postJson("/m/{$memorial->slug}/tribute", [
            'type' => $type,
            'guest_name' => 'Visitor '.$i,
            'guest_email' => "visitor{$i}@example.com",
        ])->assertOk();
    }

    expect($memorial->tributes()->count())->toBe(count(\App\Models\Tribute::TYPES));

    // 'note' was merged into 'prayer'; nothing in the UI renders or offers it any more, so
    // the API must not quietly keep minting rows of a type the page cannot display.
    $this->postJson("/m/{$memorial->slug}/tribute", [
        'type' => 'note',
        'guest_name' => 'Visitor N',
        'guest_email' => 'visitor-n@example.com',
    ])->assertStatus(422);
});

it('still accepts a tribute from a genuinely new address', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->postJson("/m/{$memorial->slug}/tribute", [
        'type' => 'prayer',
        'message' => 'Rest well',
        'guest_name' => 'New Visitor',
        'guest_email' => 'new-visitor@example.com',
    ])->assertOk();

    expect($memorial->tributes()->count())->toBe(1);
});

it('does not confirm whether an address has an account', function () {
    User::factory()->create(['email' => 'member@example.com']);
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->getJson("/m/{$memorial->slug}/subscribe/check?email=member@example.com")
        ->assertOk()
        ->assertJson(['subscribed' => false]);
});

// ─── Cross-memorial media ───────────────────────────────────────────────────

it('will not attach another memorial\'s media to a guest chapter', function () {
    $victim = Memorial::factory()->private()->create();
    $target = Memorial::factory()->create(['is_public' => true]);

    $secret = Media::create([
        'memorial_id' => $victim->id,
        'type' => 'photo',
        'path' => 'memorials/secret.jpg',
        'filename' => 'secret.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]);

    $this->postJson("/m/{$target->slug}/tribute-post", [
        'title' => 'Nice photo',
        'guest_name' => 'Thief',
        'guest_email' => 'thief@example.com',
        'media_ids' => [$secret->id],
    ])->assertOk();

    $post = $target->posts()->latest('id')->first();

    expect($post)->not->toBeNull()
        ->and($post->media->pluck('id')->all())->not->toContain($secret->id);
});

// ─── Payment status ─────────────────────────────────────────────────────────

it('does not read a missing status code as a failed payment', function () {
    $pesapal = app(PesapalService::class);

    // `null == 0` is true in PHP, so the old loose in_array() marked every response
    // without a status_code as failed — including payments still being processed.
    expect($pesapal->isPaymentFailed(['payment_status_description' => 'PENDING']))->toBeFalse()
        ->and($pesapal->isPaymentFailed(['status_code' => null]))->toBeFalse()
        ->and($pesapal->isPaymentFailed([]))->toBeFalse()
        ->and($pesapal->isPaymentFailed(['status_code' => 2]))->toBeTrue()
        ->and($pesapal->isPaymentFailed(['payment_status_description' => 'FAILED']))->toBeTrue();
});

// ─── Passwordless login ─────────────────────────────────────────────────────

it('retires earlier codes when a new one is issued', function () {
    $first = LoginCode::generate('someone@example.com');
    $second = LoginCode::generate('someone@example.com');

    expect($first->fresh()->isValid())->toBeFalse()
        ->and($second->fresh()->isValid())->toBeTrue();
});

it('rotates the session id when signing in with a code', function () {
    $user = User::factory()->create(['email' => 'code-login@example.com']);
    $code = LoginCode::generate($user->email);

    $this->get('/login/code');
    $before = session()->getId();

    $this->post('/login/verify', ['email' => $user->email, 'code' => $code->code])
        ->assertRedirect();

    expect(session()->getId())->not->toBe($before);
    $this->assertAuthenticatedAs($user);
});

// ─── Session binding ────────────────────────────────────────────────────────

it('survives the impersonation round trip', function () {
    $admin = makeAdmin('super-admin');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');
    $reseller = Reseller::factory()->create(['owner_user_id' => $owner->id]);
    $owner->update(['reseller_id' => $reseller->id]);

    // Both hops call Auth::login() mid-request and then regenerate the session. With
    // AuthenticateSession now in the web group, a mismatched password hash would log the
    // impersonator straight back out, and reading $request->user() after the swap would
    // record the wrong impersonator_id.
    $this->actingAs($admin)
        ->post("/settings/resellers/{$reseller->id}/login-as")
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($owner);
    expect(session('impersonator_id'))->toBe($admin->id);

    $this->post('/reseller/stop-impersonating')->assertRedirect(route('settings.resellers'));

    $this->assertAuthenticatedAs($admin);
});

it('keeps the current session alive after a password change', function () {
    $user = User::factory()->create(['password' => bcrypt('old-password')]);

    $this->actingAs($user)
        ->put('/profile/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect();

    // logoutOtherDevices() ends every *other* session; signing the user out of the browser
    // they just used to change it would be a regression, not a fix.
    $this->assertAuthenticatedAs($user->fresh());
});

it('locks out repeated wrong codes for one address', function () {
    $user = User::factory()->create(['email' => 'brute@example.com']);
    LoginCode::generate($user->email);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login/verify', ['email' => $user->email, 'code' => '000000']);
    }

    $this->post('/login/verify', ['email' => $user->email, 'code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});
