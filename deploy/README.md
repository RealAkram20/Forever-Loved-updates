# Release packaging notes

Files in this directory are NOT part of the running app — they are inputs for
building LaraUpdater release zips.

## upgrade.php

Copy `upgrade.php` to the **root** of the release zip. The in-app updater
(`app/Http/Controllers/Admin/LaraUpdaterController.php`) extracts it, calls
`main()`, then deletes it. It must never throw: an exception aborts the update
and restores the customer's backup.

Current hook (1.1.8): flips `QUEUE_CONNECTION=sync` → `database` in existing
installs' `.env` so background jobs run. Idempotent.

When a future release needs a new one-time migration step, extend `main()` —
keep every step individually try/caught and idempotent, because customers can
skip versions and the hook from the newest zip is the only one that runs.

## Reminders per release

- Bump `version.txt` in the zip (the updater compares and writes it).
- New DB tables/columns must arrive as additive/nullable migrations — the
  updater auto-runs `migrate --force` and restores backup on failure.
- Never rename the `payment/ipn` or `install/*` routes (CSRF exemptions in
  `bootstrap/app.php` reference them by path).
