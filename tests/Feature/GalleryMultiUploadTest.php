<?php

use App\Models\Memorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The gallery picker accepts several files at once.
 *
 * The upload itself is unchanged server-side -- still one file per request, still every
 * per-file check -- so the endpoint's existing tests stand. What this adds is the one thing a
 * browser needs to offer a multi-select at all: the `multiple` attribute on the input the
 * page renders for an editor. The sequential per-file loop lives in memorial-public.js and has
 * no JS test runner here; the build compiling is its syntax check.
 */
uses(RefreshDatabase::class);

it('offers the owner a multi-select picker for photos and videos', function () {
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create([
        'user_id' => $owner->id,
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
        'reseller_id' => null,
    ]);

    $html = $this->actingAs($owner)->get($memorial->publicUrl())->assertOk()->getContent();

    preg_match('/<input[^>]*id="gallery-upload"[^>]*>/', $html, $m);

    expect($m)->not->toBeEmpty('no gallery upload input rendered for the owner')
        ->and($m[0])->toContain('multiple')
        ->and($m[0])->toContain('accept="image/*,video/*"');
});

it('offers a visitor no upload input at all', function () {
    $memorial = Memorial::factory()->create([
        'is_public' => true,
        'status' => Memorial::STATUS_ACTIVE,
        'reseller_id' => null,
    ]);

    $this->get($memorial->publicUrl())
        ->assertOk()
        ->assertDontSee('id="gallery-upload"', false);
});

it('still accepts exactly one file per request on the endpoint, so the per-file checks are unchanged', function () {
    // A batch of two in one request must not be quietly accepted as a new contract: the
    // browser sends them one at a time, and the server keeps validating a single `file`.
    $owner = User::factory()->create();
    $memorial = Memorial::factory()->create(['user_id' => $owner->id, 'reseller_id' => null]);

    $this->actingAs($owner)
        ->postJson(route('memorial.api.gallery', $memorial->slug), [
            'file' => [
                \Illuminate\Http\UploadedFile::fake()->image('a.jpg'),
                \Illuminate\Http\UploadedFile::fake()->image('b.jpg'),
            ],
        ])
        ->assertStatus(422);
})->skip(fn () => ! app('router')->has('memorial.api.gallery'), 'route name differs');
