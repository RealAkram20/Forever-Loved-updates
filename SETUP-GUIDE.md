# Laravel Development Environment Setup Guide (Windows)

This guide prepares your Windows machine for Laravel 12 development. You have **XAMPP** and **Git** already—here's what to add.

---

## Required Tools

| Tool | Purpose | Required Version |
|------|---------|------------------|
| PHP | Laravel runtime | 8.2+ |
| Composer | PHP dependency manager | Latest |
| Node.js | Frontend build (Vite) | LTS (20+) |
| MySQL/MariaDB | Database (included in XAMPP) | - |

---

## Step 1: Add PHP to PATH (XAMPP)

XAMPP includes PHP, but it must be in your system PATH.

1. Open **System Properties** → **Environment Variables**
2. Under **System variables**, select **Path** → **Edit** → **New**
3. Add: `C:\xampp\php`
4. Click **OK** and restart your terminal

**Quick command** (run in Command Prompt as Administrator):
```cmd
setx PATH "%PATH%;C:\xampp\php"
```

Then **close and reopen** your terminal.

**Verify:**
```powershell
php -v
```
You should see PHP 8.2 or higher for Laravel 12.

---

## Step 2: Install Composer

Composer manages PHP packages (including Laravel).

### Option A: Windows Installer (recommended)

1. Download: https://getcomposer.org/Composer-Setup.exe
2. Run the installer
3. When asked for PHP path, use: `C:\xampp\php\php.exe`
4. Complete the installation

### Option B: Manual (PowerShell)
If winget doesn't have Composer, run in PowerShell:
```powershell
# Download and install to XAMPP
Invoke-WebRequest -Uri 'https://getcomposer.org/installer' -OutFile 'composer-setup.php' -UseBasicParsing
C:\xampp\php\php.exe composer-setup.php --install-dir=C:\xampp\php --filename=composer
Remove-Item composer-setup.php
```

### Option C: Chocolatey (if installed)
```powershell
choco install composer
```

**Verify:**
```powershell
composer -V
```

---

## Step 3: Install Node.js

Node.js is needed for npm and Laravel’s frontend tooling (Vite).

### Option A: Official installer (recommended)

1. Go to: https://nodejs.org/
2. Download the **LTS** version
3. Run the installer (default options are fine)
4. Restart your terminal

### Option B: winget
```powershell
winget install OpenJS.NodeJS.LTS
```

### Option C: Chocolatey
```powershell
choco install nodejs-lts
```

**Verify:**
```powershell
node -v
npm -v
```

---

## Step 4: Run the Environment Check

From your project folder:

```powershell
cd c:\xampp\htdocs\Forever-love
.\setup-dev-environment.ps1
```

If you see "All requirements met!", continue to Step 5.

---

## Step 5: Install Project Dependencies

```powershell
# PHP dependencies
composer install

# Copy environment file (if not exists)
copy .env.example .env

# Generate app key
php artisan key:generate

# Node.js dependencies
npm install
```

---

## Step 6: Configure Database (XAMPP)

1. Start **XAMPP Control Panel**
2. Start **Apache** and **MySQL**
3. Create a database (e.g. `forever_love`) in phpMyAdmin: http://localhost/phpmyadmin
4. Update `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=forever_love
DB_USERNAME=root
DB_PASSWORD=
```

5. Run migrations:

```powershell
php artisan migrate
```

---

## Step 7: Run the Project

```powershell
# Development server (PHP + Vite)
composer dev

# Or run separately:
# Terminal 1:
php artisan serve

# Terminal 2:
npm run dev
```

Visit: http://localhost:8000

---

## Optional: Install Chocolatey (Package Manager)

Chocolatey simplifies installing dev tools on Windows:

1. Open **PowerShell as Administrator**
2. Run:
```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))
```
3. Restart the terminal

Then you can install tools with `choco install <package>`.

---

## PHP 8.2 Installed (winget)

PHP 8.2 was installed via winget. To use it, either:
- **Restart your terminal** – PHP 8.2 should be in PATH
- Or use **`run-dev.bat`** in this project to start the server with PHP 8.2

If `php -v` still shows 8.0, add PHP 8.2 to the **start** of your PATH:
`C:\Users\Rio Akram\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe`

---

## Alternative: Upgrade XAMPP (if winget PHP causes issues)

Your Laravel 12 project requires **PHP 8.2+**. If XAMPP has PHP 8.0:

### Option A: Upgrade XAMPP (recommended)
1. Download XAMPP with PHP 8.2+ from https://www.apachefriends.org/download.html
2. Install (you can keep your existing `htdocs` and database)
3. Update PATH to point to the new `C:\xampp\php` folder

### Option B: Install PHP 8.2 alongside XAMPP
1. Download PHP 8.2 Thread Safe from https://windows.php.net/download/
2. Extract to `C:\php82` (or similar)
3. Add `C:\php82` to PATH **before** `C:\xampp\php` so it takes precedence
4. Copy `php.ini` from XAMPP and adjust paths: `extension_dir`, etc.

---

## Troubleshooting

### "php is not recognized"
- Add `C:\xampp\php` to PATH (see Step 1)
- Restart the terminal

### "composer is not recognized"
- Reinstall Composer and ensure it’s added to PATH
- Restart the terminal

### PHP version too old
- Update XAMPP to a version with PHP 8.2+
- Or install PHP 8.2+ from https://windows.php.net/download/

### npm install fails
- Ensure Node.js LTS is installed
- Try: `npm cache clean --force` then `npm install`

---

## Quick Reference

| Command | Purpose |
|---------|---------|
| `php artisan serve` | Start Laravel dev server |
| `npm run dev` | Build frontend assets (Vite) |
| `composer dev` | Run server + queue + logs + Vite |
| `php artisan migrate` | Run database migrations |
| `php artisan tinker` | Laravel REPL |
