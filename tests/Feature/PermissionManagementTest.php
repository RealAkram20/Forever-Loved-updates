<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['super-admin', 'admin', 'user'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

function anAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

it('lets an admin create a permission', function () {
    $this->actingAs(anAdmin())
        ->post('http://localhost/settings/permissions/permissions', ['name' => 'memorials.moderate'])
        ->assertRedirect();

    expect(Permission::where('name', 'memorials.moderate')->exists())->toBeTrue();
});

it('grants and revokes permissions on a role', function () {
    $admin = anAdmin();
    Permission::create(['name' => 'memorials.moderate', 'guard_name' => 'web']);
    Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    $role = Role::findOrCreate('moderator', 'web');

    // Grant two.
    $this->actingAs($admin)
        ->put('http://localhost/settings/permissions/roles/'.$role->id.'/permissions', [
            'permissions' => ['memorials.moderate', 'reports.view'],
        ])->assertRedirect();

    expect($role->fresh()->hasPermissionTo('memorials.moderate'))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo('reports.view'))->toBeTrue();

    // Submitting no permissions clears the role entirely.
    $this->actingAs($admin)
        ->put('http://localhost/settings/permissions/roles/'.$role->id.'/permissions', [])
        ->assertRedirect();

    expect($role->fresh()->permissions)->toHaveCount(0);
});

it('deletes a permission and detaches it from roles', function () {
    $admin = anAdmin();
    $perm = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    $role = Role::findOrCreate('moderator', 'web');
    $role->givePermissionTo($perm);

    $this->actingAs($admin)
        ->delete('http://localhost/settings/permissions/permissions/'.$perm->id)
        ->assertRedirect();

    expect(Permission::where('name', 'reports.view')->exists())->toBeFalse()
        ->and($role->fresh()->permissions)->toHaveCount(0);
});

it('forbids a non-admin from managing permissions', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->post('http://localhost/settings/permissions/permissions', ['name' => 'x.y'])
        ->assertForbidden();
});
