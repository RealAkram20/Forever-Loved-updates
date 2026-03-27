# LaraUpdater – Self-Update for Forever-love

This project uses [LaraUpdater](https://github.com/pietrocinaglia/laraupdater) to allow the live application to detect and install updates without manual file uploads.

---

## How It Works

1. **version.txt** – Stores the current app version (e.g. `1.0.0`) in the project root.
2. **Update server** – A URL (e.g. `https://yourdomain.com/updates`) that hosts:
   - `laraupdater.json` – Metadata (version, archive name, description)
   - `RELEASE-1.0.1.zip` – The update archive
3. **Check** – The app fetches `laraupdater.json` and compares versions.
4. **Update** – If a newer version exists, admins can click **Update Now** to download and install it.

---

## Configuration

### 1. Set the update URL

In `.env`:

```
LARA_UPDATER_URL=https://yourdomain.com/updates
```

Or use a subdomain: `https://updates.forever-loved.com`

### 2. Create the update folder on your server

Create a folder (e.g. `public_html/updates/` or a subdomain) that is **publicly readable** but **not writable** by the app. The app only needs to **download** from here; it does not upload.

---

## Creating an Update

### Step 1: Prepare the zip

Create a zip with the **changed files only**, preserving the same directory structure as your app.

**Include:**
- `app/` (changed files)
- `config/` (changed files)
- `database/migrations/` (new migrations)
- `public/` (changed assets)
- `resources/views/` (changed views)
- `routes/` (changed routes)
- `version.txt` (updated version)

**Exclude:**
- `.env`
- `node_modules/`
- `storage/` (user uploads)
- `vendor/` (run `composer install` on server if needed, or include vendor in zip for full replacement)

**Example structure inside the zip:**

```
RELEASE-1.0.1/
  app/
    Http/Controllers/SomeController.php
  config/
    someconfig.php
  version.txt
```

Or flat (LaraUpdater supports both):

```
app/Http/Controllers/SomeController.php
config/someconfig.php
version.txt
```

### Step 2: Create laraupdater.json

```json
{
  "version": "1.0.1",
  "archive": "RELEASE-1.0.1.zip",
  "description": "Bug fixes and improvements"
}
```

- `version` – Must be greater than the current version (e.g. `1.0.0` → `1.0.1`)
- `archive` – Exact filename of the zip
- `description` – Shown in the update notification

### Step 3: Upload

Upload both files to your update server:

```
https://yourdomain.com/updates/laraupdater.json
https://yourdomain.com/updates/RELEASE-1.0.1.zip
```

---

## Optional: upgrade.php Script

You can include an `upgrade.php` in your zip to run **custom logic** after files are extracted. The file must define a `main()` function. Note: migrations and cache clearing are already handled automatically — only use this for special cases (data transformations, column renames, etc.).

```php
<?php

function main(): bool
{
    // Runs after files are extracted, before version.txt is updated.
    // Migrations and cache clearing are handled automatically — no need to call them here.
    // Use this for one-off data fixes, column renames, etc.
    return true;
}
```

> **Important:** The function must be named `main()`. The older `beforeUpdate()`/`afterUpdate()` pattern is NOT supported.

---

## Version number (maintainers)

- **Installed version** lives in **`version.txt`** at the project root. Admin **Settings → System Updates** and `LaraUpdaterController::getCurrentVersion()` read this file.
- **Published update** metadata lives in **`public/updates/laraupdater.json`** (or your remote URL). Its `version` must be **strictly greater** than a server’s `version.txt` for that server to see “Update available.”
- **Zip name** should match the `archive` field (e.g. `RELEASE-1.1.0.zip`). After a successful update, LaraUpdater writes the new version into `version.txt`.
- **Current baseline in repo:** `1.1.6` — use `build-update.ps1` to produce the matching zip for your update host.

---

## Security

- **Who can update?** Only users with `admin` or `super-admin` role.
- **Update URL** – Keep it private or use a simple token if you prefer. The URL is in `.env` and not exposed to regular users.
- **Backup** – LaraUpdater backs up overwritten files to `backup_YYYYMMDD/` before applying updates.

---

## Artisan Commands

```bash
# Check for updates
php artisan laraupdater:check

# Show current version
php artisan laraupdater:current-version

# Install update (requires admin)
php artisan laraupdater:update
```

---

## Custom Fixes (this project)

This project uses a fully overridden `App\Http\Controllers\Admin\LaraUpdaterController` that **bypasses the vendor's update() entirely**. No vendor patches are needed — everything is handled in our custom controller.

**check():**
- Returns proper JSON, prefers local `public/updates/laraupdater.json`, handles errors gracefully
- Uses `version_compare()` (vendor uses broken string comparison)

**update():**
- Fully reimplemented — does NOT call `parent::update()`
- Uses `version_compare()` for version checking (fixes `"1.0.10" <= "1.0.9"` bug)
- Uses `ZipArchive` instead of deprecated `zip_open()` functions
- Cleans up the actual `.zip` file after extraction (vendor deletes wrong path)
- **Auto-runs `php artisan migrate --force`** after file extraction
- **Auto-clears config/route/view caches** after update
- Proper error recovery with backup restore

**getCurrentVersion():** Returns `0.0.0` if `version.txt` is missing.

**No vendor patches needed.** Safe to run `composer update` without worrying about re-applying patches.

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "Update server not reachable" | Check `LARA_UPDATER_URL`, firewall, SSL |
| "Permission denied" | Ensure user is admin/super-admin |
| "Already updated" | Version in `laraupdater.json` must be **greater** than `version.txt` |
| **404 / download failed** on Update Now | **`archive` in `laraupdater.json` must exactly match the zip filename** (e.g. `RELEASE-1.1.0.zip`). If you only uploaded a new zip but left `archive` as `RELEASE-1.0.1.zip`, the server requests the old name → 404. |
| **403** or failed download from own domain | **`public/updates/.htaccess` must not deny `.zip` files.** The app downloads the archive via HTTP from `LARA_UPDATER_URL`; blocking zips breaks self-update. Use `Options -Indexes` only, or host zips elsewhere. |
| Zip extraction fails | Ensure zip structure matches app (no extra root folder unless expected) |
| Folders like **`-1.1.5/`** or **`RELEASE-x/`** inside `public_html` | Caused by **broken zip entry names** built on Windows when `Resolve-Path` returned a short path (`RIOAKR~1`) but file paths were long — fixed in `build-update.ps1` (uses `Get-Item` for the temp root). **Rebuild** the zip with the current script, re-upload, and delete the stray folders on the server. LaraUpdater also strips legacy `-X.Y.Z/` and `RELEASE-*` wrappers when extracting. |
| Maintenance mode stuck | Run `php artisan up` manually |

---

## Build Script

Use `build-update.ps1` to create update packages:

```powershell
# Auto-detect changed files via git diff (recommended)
.\build-update.ps1

# Include ALL updatable directories (for major releases)
.\build-update.ps1 -All

# Custom description
.\build-update.ps1 -Description "Fixed payment bug"
```

The script automatically:
- Detects changed files via `git diff` (or includes all with `-All`)
- Always includes all migrations (critical for updates)
- Always includes `version.txt`
- Creates the zip and `laraupdater.json` in `public/updates/`

**Full release (`-All`):** `app/`, `bootstrap/` (except generated `bootstrap/cache/*.php`), `config/`, `database/` (migrations, seeders, factories, root files — no `.sqlite`), `resources/css/`, `resources/js/`, `resources/views/`, `routes/`, almost all of **`public/`** except `public/storage/` (uploads) and `public/updates/RELEASE-*.zip`, plus root files: `artisan`, `composer.json`, `composer.lock`, `package*.json`, `vite.config.js`, PostCSS/Tailwind configs, `version.txt`.

**Still excluded:** `vendor/`, `node_modules/`, `storage/`, `.env`, `public/storage/`, nested release zips.

Before `-All`, run **`npm run build`** so `public/build` matches your CSS/JS. If `composer.json` / `composer.lock` changed, SSH in after update and run **`composer install --no-dev --optimize-autoloader`**.

## Deploy Script

The `deploy-hostinger.ps1` script includes `version.txt` in the deploy zip. After deploying, ensure `version.txt` on the server matches your release version.
