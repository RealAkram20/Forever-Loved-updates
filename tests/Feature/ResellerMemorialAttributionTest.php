<?php

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * reseller_id used to be stamped in exactly one place — the reseller's own "create a memorial
 * for a client" screen. Every other creation path produced an untenanted memorial, so a
 * reseller's client making their own memorial went uncounted against the tier allowance and
 * storage cap the platform bills on, and was unreachable on the reseller's own subdomain.
 */
function attributionTenant(string $slug = 'acme'): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => ucfirst($slug).' Funeral Home',
        'slug' => $slug,
        'owner_user_id' => $owner->id,
        'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

function attributionClient(Reseller $reseller): User
{
    $client = User::factory()->create([
        'reseller_id' => $reseller->id,
        'original_reseller_id' => $reseller->id,
    ]);
    $client->assignRole('user');

    return $client;
}

it('attributes a memorial to the tenant its owner belongs to', function () {
    $acme = attributionTenant();
    $client = attributionClient($acme);

    // No reseller_id passed — exactly what MemorialController::store() does.
    $memorial = Memorial::create([
        'user_id' => $client->id,
        'slug' => 'jane-doe',
        'full_name' => 'Jane Doe',
        'title' => 'In Loving Memory of Jane Doe',
    ]);

    expect($memorial->reseller_id)->toBe($acme->id)
        ->and($memorial->original_reseller_id)->toBe($acme->id);
});

it('counts a client-created memorial against the reseller allowance', function () {
    $acme = attributionTenant();
    $client = attributionClient($acme);

    Memorial::create([
        'user_id' => $client->id, 'slug' => 'jane-doe',
        'full_name' => 'Jane Doe', 'title' => 'x',
    ]);

    // The billing leak: this used to report 0, so overage was never charged.
    expect($acme->fresh()->memorialsUsed())->toBe(1);
});

it('leaves a direct platform user memorial untenanted', function () {
    Role::findOrCreate('user', 'web');
    $user = User::factory()->create(['reseller_id' => null]);

    $memorial = Memorial::create([
        'user_id' => $user->id, 'slug' => 'jane-doe',
        'full_name' => 'Jane Doe', 'title' => 'x',
    ]);

    expect($memorial->reseller_id)->toBeNull();
});

it('does not override an explicitly set reseller', function () {
    $acme = attributionTenant('acme');
    $beta = attributionTenant('beta');
    $client = attributionClient($acme);

    // Reseller\DashboardController::storeMemorial() sets this itself; the hook must defer.
    $memorial = Memorial::create([
        'user_id' => $client->id, 'reseller_id' => $beta->id,
        'slug' => 'jane-doe', 'full_name' => 'Jane Doe', 'title' => 'x',
    ]);

    expect($memorial->reseller_id)->toBe($beta->id);
});

it('gives a client-created memorial the reseller white-labeled address', function () {
    // Both pinned: reseller.domain derives from APP_URL now, so building APP_URL from it
    // was circular and resolved to the bare 'localhost' the test environment runs on.
    config(['reseller.domain' => 'example.test', 'app.url' => 'https://example.test']);
    $acme = attributionTenant();
    $client = attributionClient($acme);

    $memorial = Memorial::create([
        'user_id' => $client->id, 'slug' => 'jane-doe',
        'full_name' => 'Jane Doe', 'title' => 'x',
    ]);

    expect($memorial->fresh()->publicUrl())->toContain('acme.'.config('reseller.domain').'/jane-doe');
});

it('serves a client-created memorial on the reseller subdomain', function () {
    $acme = attributionTenant();
    $client = attributionClient($acme);

    Memorial::create([
        'user_id' => $client->id, 'slug' => 'jane-doe',
        'full_name' => 'Jane Doe', 'title' => 'x',
        'is_public' => true, 'status' => Memorial::STATUS_ACTIVE,
    ]);

    // Previously 404 — the memorial had no reseller_id, and showForReseller scopes to it.
    $this->get('http://acme.'.config('reseller.domain').'/jane-doe')->assertOk();
});

it('shows a client-created memorial in the reseller memorials list', function () {
    $acme = attributionTenant();
    $client = attributionClient($acme);

    Memorial::create([
        'user_id' => $client->id, 'slug' => 'jane-doe',
        'full_name' => 'Jane Doe', 'title' => 'In Loving Memory of Jane Doe',
    ]);

    $this->actingAs($acme->owner)
        ->get('http://localhost/reseller/memorials')
        ->assertOk()
        ->assertSee('Jane Doe');
});

it('stops attributing once a client is rolled over to the platform', function () {
    $acme = attributionTenant();
    $client = attributionClient($acme);

    // What Admin\ResellerController::rollover() does to a client.
    $client->update(['reseller_id' => null]);

    $memorial = Memorial::create([
        'user_id' => $client->fresh()->id, 'slug' => 'jane-doe',
        'full_name' => 'Jane Doe', 'title' => 'x',
    ]);

    expect($memorial->reseller_id)->toBeNull();
});

it('backfills memorials created before attribution existed', function () {
    $acme = attributionTenant();
    $client = attributionClient($acme);

    // Simulates the pre-fix state: created, then stripped, as the old code path left it.
    $memorial = Memorial::create([
        'user_id' => $client->id, 'slug' => 'jane-doe',
        'full_name' => 'Jane Doe', 'title' => 'x',
    ]);
    $memorial->updateQuietly(['reseller_id' => null, 'original_reseller_id' => null]);

    expect($acme->fresh()->memorialsUsed())->toBe(0);

    $this->artisan('memorials:backfill-reseller-attribution')->assertSuccessful();

    expect($memorial->fresh()->reseller_id)->toBe($acme->id)
        ->and($acme->fresh()->memorialsUsed())->toBe(1);
});

it('leaves rolled-over memorials alone when backfilling', function () {
    $acme = attributionTenant();
    $client = attributionClient($acme);

    $memorial = Memorial::create([
        'user_id' => $client->id, 'slug' => 'jane-doe',
        'full_name' => 'Jane Doe', 'title' => 'x',
    ]);
    // A rollover nulls the memorial's reseller_id and the client's, but keeps
    // original_reseller_id so restore() can reverse it. Backfill must not undo that.
    $memorial->updateQuietly(['reseller_id' => null]);
    $client->update(['reseller_id' => null]);

    $this->artisan('memorials:backfill-reseller-attribution')->assertSuccessful();

    expect($memorial->fresh()->reseller_id)->toBeNull();
});

it('writes nothing on a dry run', function () {
    $acme = attributionTenant();
    $client = attributionClient($acme);

    $memorial = Memorial::create([
        'user_id' => $client->id, 'slug' => 'jane-doe',
        'full_name' => 'Jane Doe', 'title' => 'x',
    ]);
    $memorial->updateQuietly(['reseller_id' => null]);

    $this->artisan('memorials:backfill-reseller-attribution', ['--dry-run' => true])->assertSuccessful();

    expect($memorial->fresh()->reseller_id)->toBeNull();
});
