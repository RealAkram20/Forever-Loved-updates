<?php

use App\Models\LoginCode;
use App\Models\Memorial;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'super-admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

// ─── A guest's first write is their signup ──────────────────────────────────

it('signs a guest in when their first story creates their account', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $response = $this->postJson("/m/{$memorial->slug}/tribute-post", [
        'content' => 'She sang in the kitchen every morning.',
        'guest_name' => 'Amina',
        'guest_email' => 'amina@example.com',
    ])->assertOk()->assertJson(['success' => true, 'signed_in' => true]);

    // The page's old CSRF token died with the session rotation; the response must carry
    // the replacement or every later request from that page 419s.
    expect($response->json('csrf'))->toBeString()->not->toBe('');

    $this->assertAuthenticated();

    $user = User::where('email', 'amina@example.com')->first();
    expect($user)->not->toBeNull()
        ->and(Auth::id())->toBe($user->id)
        ->and($user->hasRole('user'))->toBeTrue();
});

it('lets the same browser comment right after the story, asked for nothing', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->postJson("/m/{$memorial->slug}/tribute-post", [
        'content' => 'The first thing I ever said about him.',
        'guest_name' => 'Joseph',
        'guest_email' => 'joseph@example.com',
    ])->assertOk();

    $post = $memorial->posts()->latest('id')->first();

    // No guest_name, no guest_email: the session from the write is the identity now.
    $this->postJson("/m/{$memorial->slug}/posts/{$post->id}/comments", [
        'content' => 'And a comment straight after.',
    ])->assertOk()->assertJson(['success' => true]);

    expect($post->comments()->count())->toBe(1)
        ->and($post->comments()->first()->user_id)->toBe(User::where('email', 'joseph@example.com')->first()->id);
});

it('signs a guest in when their first heart creates their account', function () {
    $memorial = Memorial::factory()->create(['is_public' => true]);
    $post = $memorial->posts()->create(['type' => 'text', 'content' => 'A story to heart.', 'is_published' => true]);

    $response = $this->postJson("/m/{$memorial->slug}/reaction", [
        'reactionable_type' => 'post',
        'reactionable_id' => $post->id,
        'type' => 'like',
        'guest_name' => 'Grace',
        'guest_email' => 'grace@example.com',
    ])->assertOk()->assertJson(['success' => true, 'action' => 'added', 'signed_in' => true]);

    expect($response->json('csrf'))->toBeString()->not->toBe('');
    $this->assertAuthenticated();
});

it('still refuses a guest write claiming a registered address, without signing anyone in', function () {
    User::factory()->create(['email' => 'member@example.com']);
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->postJson("/m/{$memorial->slug}/tribute-post", [
        'content' => 'Not mine to sign.',
        'guest_name' => 'Impostor',
        'guest_email' => 'member@example.com',
    ])->assertStatus(422)->assertJson(['requires_login' => true]);

    $this->assertGuest();
});

// ─── Every sign-in is remembered ────────────────────────────────────────────

it('leaves the remember cookie on a code sign-in', function () {
    $user = User::factory()->create();
    $code = LoginCode::generate($user->email);

    $this->post('/login/verify', ['email' => $user->email, 'code' => $code->code])
        ->assertRedirect()
        ->assertCookie(Auth::guard('web')->getRecallerName());
});

it('leaves the remember cookie on a password sign-in, with no checkbox sent', function () {
    $user = User::factory()->create(['password' => bcrypt('secret-word-9')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-word-9'])
        ->assertRedirect()
        ->assertCookie(Auth::guard('web')->getRecallerName());
});

// ─── Sign-in returns you to the page that sent you ──────────────────────────

it('returns a code sign-in to the memorial that sent it', function () {
    $user = User::factory()->create();
    $memorial = Memorial::factory()->create(['is_public' => true]);

    $this->get('/login/code?return=/m/'.$memorial->slug);

    $code = LoginCode::generate($user->email);
    $this->post('/login/verify', ['email' => $user->email, 'code' => $code->code])
        ->assertRedirect('/m/'.$memorial->slug);
});

it('refuses an absolute url as a return target', function (string $evil) {
    $user = User::factory()->create();

    $this->get('/login/code?return='.urlencode($evil));

    $code = LoginCode::generate($user->email);
    $redirect = $this->post('/login/verify', ['email' => $user->email, 'code' => $code->code]);

    // Falls through to the designated place, never off-site.
    $redirect->assertRedirect();
    expect($redirect->headers->get('Location'))->not->toContain('evil');
})->with([
    'absolute' => ['https://evil.example.com/phish'],
    'protocol-relative' => ['//evil.example.com/phish'],
    'backslash' => ['/\\evil.example.com'],
]);

// ─── Google One Tap ─────────────────────────────────────────────────────────

function enableGoogleLogin(): void
{
    SystemSetting::set('oauth.google_enabled', '1', 'oauth', 'boolean');
    SystemSetting::set('oauth.google_client_id', 'test-client-id.apps.googleusercontent.com', 'oauth', 'string');
    SystemSetting::set('oauth.google_client_secret', 'test-secret', 'oauth', 'string');
    SystemSetting::clearCache();
}

it('signs a visitor in from a verified One Tap credential', function () {
    enableGoogleLogin();

    Http::fake([
        'oauth2.googleapis.com/tokeninfo*' => Http::response([
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client-id.apps.googleusercontent.com',
            'sub' => '108977041234567890123',
            'email' => 'onetap@example.com',
            'email_verified' => 'true',
            'name' => 'One Tap Visitor',
        ]),
    ]);

    $this->postJson('/auth/google/one-tap', ['credential' => 'fake-jwt'])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertAuthenticated();

    // No email_verified_at assertion: the column is not fillable, so the OAuth callback
    // flow has never actually persisted it either — One Tap simply matches it.
    $user = User::where('email', 'onetap@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('108977041234567890123');
});

it('rejects a One Tap credential minted for another site', function () {
    enableGoogleLogin();

    Http::fake([
        'oauth2.googleapis.com/tokeninfo*' => Http::response([
            'iss' => 'https://accounts.google.com',
            'aud' => 'someone-elses-client-id.apps.googleusercontent.com',
            'sub' => '1',
            'email' => 'onetap@example.com',
            'email_verified' => 'true',
        ]),
    ]);

    $this->postJson('/auth/google/one-tap', ['credential' => 'fake-jwt'])
        ->assertStatus(422);

    $this->assertGuest();
});

it('rejects a One Tap credential whose email google has not verified', function () {
    enableGoogleLogin();

    Http::fake([
        'oauth2.googleapis.com/tokeninfo*' => Http::response([
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client-id.apps.googleusercontent.com',
            'sub' => '1',
            'email' => 'onetap@example.com',
            'email_verified' => 'false',
        ]),
    ]);

    $this->postJson('/auth/google/one-tap', ['credential' => 'fake-jwt'])
        ->assertStatus(422);

    $this->assertGuest();
});

it('matches One Tap to an existing account by email and adopts the google id', function () {
    enableGoogleLogin();

    $existing = User::factory()->create(['email' => 'family@example.com', 'google_id' => null]);

    Http::fake([
        'oauth2.googleapis.com/tokeninfo*' => Http::response([
            'iss' => 'accounts.google.com',
            'aud' => 'test-client-id.apps.googleusercontent.com',
            'sub' => '42',
            'email' => 'family@example.com',
            'email_verified' => true,
            'name' => 'Someone Else Entirely',
        ]),
    ]);

    $this->postJson('/auth/google/one-tap', ['credential' => 'fake-jwt'])->assertOk();

    expect(Auth::id())->toBe($existing->id)
        ->and($existing->fresh()->google_id)->toBe('42')
        ->and(User::count())->toBe(1);
});

it('serves no One Tap endpoint when google login is disabled', function () {
    $this->postJson('/auth/google/one-tap', ['credential' => 'fake-jwt'])
        ->assertNotFound();
});
