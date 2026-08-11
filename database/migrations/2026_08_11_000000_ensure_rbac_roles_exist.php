<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The roles the application's own code names, guaranteed to exist.
 *
 * These lived only in RoleSeeder, and nothing on the deployment path runs seeders —
 * the container entrypoint migrates and nothing more. So the roles existed on any
 * install that had been seeded once, and silently did not on any install restored
 * from an older database. Moving production onto a new server found this the hard
 * way: the reseller tables migrated across fine, the `reseller` role did not, and
 * creating a reseller died on Spatie's "There is no role named `reseller`".
 *
 * A migration rather than a seeder because that is what actually runs. The seeder
 * stays for fresh local installs; the two agree, and this one is the guarantee.
 *
 * Only ever inserts. A role already present is left exactly as it is, along with
 * every assignment hanging off it.
 */
return new class extends Migration
{
    private const ROLES = [
        'super-admin',
        'admin',
        'user',
        'contributor',
        'reseller',
    ];

    public function up(): void
    {
        $existing = DB::table('roles')->where('guard_name', 'web')->pluck('name')->all();

        $missing = array_values(array_diff(self::ROLES, $existing));

        if ($missing === []) {
            return;
        }

        $now = now();

        DB::table('roles')->insert(array_map(fn (string $name) => [
            'name' => $name,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], $missing));

        // Spatie caches the whole role/permission set, so a role inserted underneath
        // it stays invisible until the cache expires — which on a live site means the
        // deploy appears to have changed nothing at all.
        Cache::forget(config('permission.cache.key', 'spatie.permission.cache'));
    }

    /**
     * Deliberately empty. These roles are what the application's authorisation checks
     * are written against, and users are assigned to them; dropping one on a rollback
     * would revoke real people's access to fix a problem that is not theirs.
     */
    public function down(): void
    {
        //
    }
};
