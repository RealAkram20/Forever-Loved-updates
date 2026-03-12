# Hostinger Shared Hosting – Simple Deploy

Deploy like your other PHP projects: **zip → upload → .env → import SQL → done.**

---

## What’s different from plain PHP

Laravel needs **one extra setting**:

- **Document root** must point to the `public` folder (not the project root).  
  In Hostinger: **Domains** → your domain → **Document Root** → set to `public_html/public`.

Everything else is the same: upload files, configure `.env`, import SQL.

---

## Step 1: Prepare the zip (once, on your PC)

1. In the project folder, run:
   ```powershell
   npm run build
   ```
   (Creates `public/build` for CSS/JS.)

2. Zip the `Forever-love` folder. Exclude:
   - `node_modules`
   - `.git`
   - `.env` (use `.env.example` on the server)

   Or run: `.\deploy-hostinger.ps1` to create a ready-made zip.

---

## Step 2: Upload

1. Hostinger **hPanel** → **File Manager** → `public_html`
2. Upload the zip and extract so `app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`, `storage`, `vendor` are directly in `public_html`.

---

## Step 3: Set document root

**Domains** → your domain → **Document Root** → `public_html/public`

---

## Step 4: Configure .env

1. In File Manager, copy `.env.example` to `.env`
2. Edit `.env` and set at least:

   ```
   APP_URL=https://yourdomain.com
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:xxxx   ← see below
   DB_DATABASE=your_db_name
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   QUEUE_CONNECTION=sync
   ```

**APP_KEY:** Run locally in the project folder:
```powershell
php artisan key:generate --show
```
Copy the output and paste it into `APP_KEY=` in `.env` on the server.

---

## Step 5: Import database

In Hostinger **phpMyAdmin**, import your SQL dump.

---

## Step 6: Storage link (for profile photos)

If users upload photos, run once in Hostinger **Terminal** (or SSH):

```bash
cd ~/public_html
php artisan storage:link
```

---

## Done

Visit your domain. The app should be live.

---

## Optional: cache (for performance)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
