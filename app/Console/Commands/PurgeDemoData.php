<?php

namespace App\Console\Commands;

use App\Models\Memorial;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes everything DemoDataSeeder created.
 *
 * That seeder makes accounts — including admin ones — with the password "password". Left
 * in a production database they are publicly guessable credentials with real privileges,
 * so this exists to get rid of them deliberately rather than by hand at 2am.
 *
 * Reports by default and refuses to delete without --force. It identifies demo records by
 * the reserved example.* domains from RFC 2606, which real users cannot register.
 */
class PurgeDemoData extends Command
{
    protected $signature = 'demo:purge {--force : Actually delete. Without this the command only reports.}';

    protected $description = 'Remove seeded demo users, resellers and memorials from the database';

    /** RFC 2606 reserved domains — safe to treat as "not a real customer". */
    private const DEMO_EMAIL_PATTERNS = ['%@example.com', '%@example.org', '%@example.net', '%@example.edu'];

    public function handle(): int
    {
        $users = User::where(function ($q) {
            foreach (self::DEMO_EMAIL_PATTERNS as $pattern) {
                $q->orWhere('email', 'like', $pattern);
            }
        })->get();

        if ($users->isEmpty()) {
            $this->info('No demo accounts found. Nothing to purge.');

            return self::SUCCESS;
        }

        $userIds = $users->pluck('id');
        $resellers = Reseller::whereIn('owner_user_id', $userIds)->get();
        $memorials = Memorial::whereIn('user_id', $userIds)
            ->orWhereIn('reseller_id', $resellers->pluck('id'))
            ->get();

        $this->newLine();
        $this->line('Demo records found:');
        $this->table(['Type', 'Count'], [
            ['Users', $users->count()],
            ['  of which admin/super-admin', $users->filter(fn ($u) => $u->hasRole(['admin', 'super-admin']))->count()],
            ['Resellers', $resellers->count()],
            ['Memorials', $memorials->count()],
        ]);

        // Named explicitly: privileged demo accounts are the reason this command exists,
        // and an operator should see exactly which ones before agreeing to anything.
        $privileged = $users->filter(fn ($u) => $u->hasRole(['admin', 'super-admin']));
        if ($privileged->isNotEmpty()) {
            $this->warn('Privileged demo accounts (these have the password "password"):');
            foreach ($privileged as $u) {
                $this->warn('  '.$u->email.'  ['.$u->roles->pluck('name')->implode(', ').']');
            }
            $this->newLine();
        }

        if (! $this->option('force')) {
            $this->comment('Nothing deleted. Re-run with --force to remove these.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Permanently delete every record listed above?', false)) {
            $this->info('Cancelled. Nothing deleted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($users, $resellers, $memorials) {
            // Order matters: memorials and resellers first, so nothing is left pointing at
            // a user row that has already gone.
            Memorial::whereIn('id', $memorials->pluck('id'))->delete();
            Reseller::whereIn('id', $resellers->pluck('id'))->delete();
            User::whereIn('id', $users->pluck('id'))->delete();
        });

        $this->newLine();
        $this->info("Purged {$users->count()} users, {$resellers->count()} resellers and {$memorials->count()} memorials.");
        $this->comment('Verify your own admin account still works before closing this session.');

        return self::SUCCESS;
    }
}
