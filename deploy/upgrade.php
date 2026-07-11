<?php

/**
 * LaraUpdater upgrade hook — copy this file to the ROOT of the release zip.
 *
 * The updater (app/Http/Controllers/Admin/LaraUpdaterController.php) extracts
 * it, require()s it, and calls main() BEFORE running migrations. An exception
 * thrown here aborts the whole update and restores the backup, so every code
 * path must swallow its own failures: this hook only performs nice-to-have
 * env migrations, never anything the update depends on.
 *
 * 1.1.8: existing installs were written with QUEUE_CONNECTION=sync by the old
 * installer; flip them to the database driver so background jobs work.
 * Idempotent — safe to run on any version, any number of times.
 */
function main(): void
{
    try {
        $envPath = base_path('.env');
        if (! is_file($envPath) || ! is_writable($envPath)) {
            return;
        }

        $env = file_get_contents($envPath);
        if ($env === false) {
            return;
        }

        if (preg_match('/^QUEUE_CONNECTION=sync\s*$/m', $env)) {
            $env = preg_replace('/^QUEUE_CONNECTION=sync\s*$/m', 'QUEUE_CONNECTION=database', $env, 1);
        } elseif (! preg_match('/^QUEUE_CONNECTION=/m', $env)) {
            $env .= "\nQUEUE_CONNECTION=database\n";
        } else {
            return; // already database (or deliberately customized) — leave it alone
        }

        file_put_contents($envPath, $env);
        // config cache is rebuilt by the updater's optimize:clear later in the flow
    } catch (\Throwable $e) {
        // Never abort the update for an env flip; the dashboard health banner
        // will tell the admin if the queue still isn't running.
        try {
            \Illuminate\Support\Facades\Log::warning('upgrade.php env migration skipped', ['error' => $e->getMessage()]);
        } catch (\Throwable $ignored) {
        }
    }
}
