<?php

use App\Models\Memorial;
use App\Models\Notification;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The client roster used to be add-and-remove only. The update route existed from the
 * start but nothing ever linked to it, so a mistyped family name or a changed email
 * could not be corrected from anywhere in the product, and an invite that went out
 * before SMTP worked could not be sent again.
 */
function clientTenant(): Reseller
{
    Role::findOrCreate('reseller', 'web');
    Role::findOrCreate('user', 'web');

    $owner = User::factory()->create();
    $owner->assignRole('reseller');

    $reseller = Reseller::create([
        'name' => 'Roster Funeral Home', 'slug' => 'roster-'.substr(uniqid(), -8),
        'owner_user_id' => $owner->id, 'status' => Reseller::STATUS_ACTIVE,
    ]);

    $owner->update(['reseller_id' => $reseller->id, 'original_reseller_id' => $reseller->id]);

    return $reseller->fresh();
}

function rosterClient(Reseller $reseller, string $email = 'family@example.test'): User
{
    $client = User::factory()->create(['name' => 'Original Name', 'email' => $email, 'reseller_id' => $reseller->id]);
    $client->assignRole('user');

    return $client;
}

it('adds a client without a password so the emailed code is the way in', function () {
    $reseller = clientTenant();

    $this->actingAs($reseller->owner)
        ->post('http://localhost/reseller/clients', ['name' => 'New Family', 'email' => 'new@example.test'])
        ->assertRedirect();

    $client = User::where('email', 'new@example.test')->firstOrFail();

    expect($client->password)->toBeNull()
        ->and($client->reseller_id)->toBe($reseller->id)
        ->and(Notification::where('user_id', $client->id)->where('type', 'account_invite')->exists())->toBeTrue();
});

it('offers edit, resend and detail links on the roster', function () {
    $reseller = clientTenant();
    $client = rosterClient($reseller);

    $this->actingAs($reseller->owner)
        ->get('http://localhost/reseller/clients')
        ->assertOk()
        ->assertSee(route('reseller.clients.show', $client), false)
        ->assertSee(route('reseller.clients.resend-invite', $client), false)
        ->assertSee('Edit');
});

it('corrects a client name and email from the roster', function () {
    $reseller = clientTenant();
    $client = rosterClient($reseller);

    $this->actingAs($reseller->owner)
        ->put("http://localhost/reseller/clients/{$client->id}", ['name' => 'Corrected Name', 'email' => 'corrected@example.test'])
        ->assertRedirect();

    expect($client->fresh()->name)->toBe('Corrected Name')
        ->and($client->fresh()->email)->toBe('corrected@example.test');
});

it('reports an edit failure in its own error bag', function () {
    $reseller = clientTenant();
    $client = rosterClient($reseller);
    $taken = rosterClient($reseller, 'taken@example.test');

    // A shared bag would light up the add-client modal instead of the edit one.
    $this->actingAs($reseller->owner)
        ->put("http://localhost/reseller/clients/{$client->id}", ['name' => 'Whoever', 'email' => $taken->email])
        ->assertSessionHasErrorsIn('updateClient', ['email']);

    expect($client->fresh()->email)->toBe('family@example.test');
});

it('resends an invitation', function () {
    $reseller = clientTenant();
    $client = rosterClient($reseller);

    $this->actingAs($reseller->owner)
        ->post("http://localhost/reseller/clients/{$client->id}/resend-invite")
        ->assertRedirect();

    expect(Notification::where('user_id', $client->id)->where('type', 'account_invite')->count())->toBe(1);
});

it('shows a client detail page listing only this reseller\'s memorials', function () {
    $reseller = clientTenant();
    $other = clientTenant();
    $client = rosterClient($reseller);

    Memorial::create([
        'user_id' => $client->id, 'reseller_id' => $reseller->id, 'slug' => 'ours',
        'title' => 'In Loving Memory of Our Charge', 'full_name' => 'Our Charge',
        'first_name' => 'Our', 'last_name' => 'Charge', 'theme' => 'free', 'plan' => 'free',
        'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    // Same person, a memorial held under a previous provider — not this one's to show.
    Memorial::create([
        'user_id' => $client->id, 'reseller_id' => $other->id, 'slug' => 'theirs',
        'title' => 'In Loving Memory of Their Charge', 'full_name' => 'Their Charge',
        'first_name' => 'Their', 'last_name' => 'Charge', 'theme' => 'free', 'plan' => 'free',
        'status' => Memorial::STATUS_ACTIVE, 'is_public' => true,
    ]);

    $this->actingAs($reseller->owner)
        ->get("http://localhost/reseller/clients/{$client->id}")
        ->assertOk()
        ->assertSee('Our Charge')
        ->assertDontSee('Their Charge');
});

it('refuses every client route across tenants', function () {
    $reseller = clientTenant();
    $other = clientTenant();
    $outsider = rosterClient($other, 'outsider@example.test');

    $this->actingAs($reseller->owner)->get("http://localhost/reseller/clients/{$outsider->id}")->assertForbidden();
    $this->actingAs($reseller->owner)->post("http://localhost/reseller/clients/{$outsider->id}/resend-invite")->assertForbidden();
    $this->actingAs($reseller->owner)
        ->put("http://localhost/reseller/clients/{$outsider->id}", ['name' => 'Hijack', 'email' => 'hijack@example.test'])
        ->assertForbidden();

    expect($outsider->fresh()->name)->toBe('Original Name');
});

it('refuses to touch the reseller owner account through the client routes', function () {
    $reseller = clientTenant();

    // The owner is not a 'user'-role client. Renaming or re-emailing them here would
    // be an account takeover dressed as an edit.
    $this->actingAs($reseller->owner)->get("http://localhost/reseller/clients/{$reseller->owner_user_id}")->assertForbidden();
    $this->actingAs($reseller->owner)->post("http://localhost/reseller/clients/{$reseller->owner_user_id}/resend-invite")->assertForbidden();
});
