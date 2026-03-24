# Deploy Forever-love to Hostinger - builds and creates deploy zip
# Run from project root: .\deploy-hostinger.ps1

$ErrorActionPreference = "Stop"
$projectRoot = $PSScriptRoot
$zipPath = "c:\xampp\htdocs\Forever-love-deploy.zip"

Write-Host "`n=== Forever-love Hostinger Deploy ===" -ForegroundColor Cyan
Set-Location $projectRoot

# 0. Pre-flight checks
$geoDb = Join-Path $projectRoot "database\geo\world.sqlite3"
if (-not (Test-Path $geoDb)) {
    Write-Host "`n[!] database\geo\world.sqlite3 is MISSING." -ForegroundColor Red
    Write-Host "    Run: php artisan geo:import" -ForegroundColor Yellow
    Write-Host "    The country/state/city selects will not work without this file." -ForegroundColor Yellow
    $continue = Read-Host "Continue without it? (y/N)"
    if ($continue -ne 'y') { exit 1 }
}

# 1. Build frontend assets
Write-Host "`n[1/4] Building Vite assets..." -ForegroundColor Yellow
npm run build
if ($LASTEXITCODE -ne 0) { throw "npm run build failed" }

# 2. Production composer install (optional - uses existing vendor if composer fails)
Write-Host "`n[2/4] Installing production dependencies..." -ForegroundColor Yellow
$composerCmd = $null
if (Get-Command composer -ErrorAction SilentlyContinue) { $composerCmd = "composer" }
elseif (Test-Path "c:\xampp\htdocs\composer.phar") { $composerCmd = "php c:\xampp\htdocs\composer.phar" }
elseif (Test-Path "$projectRoot\composer.phar") { $composerCmd = "php $projectRoot\composer.phar" }
if ($composerCmd) {
    try {
        Invoke-Expression "$composerCmd install --no-dev --optimize-autoloader --no-interaction"
    } catch {
        Write-Host "Composer install failed - using existing vendor folder" -ForegroundColor Yellow
    }
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Composer install failed - using existing vendor folder" -ForegroundColor Yellow
    }
} else {
    Write-Host "Composer not found - using existing vendor folder" -ForegroundColor Yellow
}

# 3. Clean compiled views before packaging
Write-Host "`n[3/4] Cleaning compiled views..." -ForegroundColor Yellow
$viewsDir = Join-Path $projectRoot "storage\framework\views"
Get-ChildItem -Path $viewsDir -Filter "*.php" -ErrorAction SilentlyContinue | Remove-Item -Force

# 4. Create zip (exclude node_modules, .git, etc.)
Write-Host "`n[4/4] Creating deploy zip..." -ForegroundColor Yellow
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

$includeDirs = @(
    "app", "bootstrap", "config", "database", "public", "resources", "routes", "storage", "vendor"
)
$includeFiles = @(
    ".env.production", ".htaccess", "artisan", "composer.json", "composer.lock", "package.json", "version.txt"
)

$tempDir = Join-Path $env:TEMP "Forever-love-deploy"
if (Test-Path $tempDir) { Remove-Item $tempDir -Recurse -Force }
New-Item -ItemType Directory -Path $tempDir | Out-Null

foreach ($dir in $includeDirs) {
    $src = Join-Path $projectRoot $dir
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination (Join-Path $tempDir $dir) -Recurse -Force
    }
}

foreach ($file in $includeFiles) {
    $src = Join-Path $projectRoot $file
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $tempDir -Force
    } else {
        Write-Host "  [skip] $file not found" -ForegroundColor Yellow
    }
}

# Remove files that must NOT be in the deploy package
$removeFiles = @(
    "public\hot",
    "storage\installed",
    "storage\logs\laravel.log"
)
foreach ($f in $removeFiles) {
    $fPath = Join-Path $tempDir $f
    if (Test-Path $fPath) {
        Remove-Item $fPath -Force
        Write-Host "  Removed $f" -ForegroundColor Gray
    }
}

# Patch public/.htaccess for production (domain root): RewriteBase /Forever-love/ -> /
$htaccessPath = Join-Path $tempDir "public\.htaccess"
if (Test-Path $htaccessPath) {
    $content = Get-Content $htaccessPath -Raw
    $content = $content -replace 'RewriteBase\s+/Forever-love/', 'RewriteBase /'
    Set-Content -Path $htaccessPath -Value $content -NoNewline
    Write-Host "  Patched public/.htaccess: RewriteBase -> /" -ForegroundColor Gray
}

# Create zip with forward slashes (/) for Linux compatibility
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$tempDirFull = (Resolve-Path $tempDir).Path
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
try {
    Get-ChildItem -Path $tempDir -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring($tempDirFull.Length + 1) -replace '\\', '/'
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $relativePath, 'Optimal') | Out-Null
    }
} finally { $zip.Dispose() }
Remove-Item $tempDir -Recurse -Force

$sizeMB = [math]::Round((Get-Item $zipPath).Length / 1MB, 1)
Write-Host "`n=== Done ===" -ForegroundColor Green
Write-Host "Zip: $zipPath ($sizeMB MB)" -ForegroundColor Green
Write-Host "`nIncluded:" -ForegroundColor Cyan
Write-Host '  - .env.production (copy to .env on server and fill in credentials)' -ForegroundColor Gray
Write-Host "  - version.txt (for update system)" -ForegroundColor Gray
Write-Host "  - public/updates/ (update packages)" -ForegroundColor Gray
if (Test-Path $geoDb) {
    Write-Host "  - database/geo/world.sqlite3 (country/state/city data)" -ForegroundColor Gray
}
Write-Host "`nAfter upload (Hostinger: docroot = public_html, cannot point to /public):" -ForegroundColor Cyan
Write-Host "  1. Extract the zip *into* public_html (app/, public/, vendor/, .htaccess at same level)" -ForegroundColor Gray
Write-Host "  2. Leave document root as public_html — the root .htaccess routes requests into public/" -ForegroundColor Gray
Write-Host '  3. Create .env in public_html/ (use .env.production as a template and fill values)' -ForegroundColor Gray
Write-Host "  4. Visit https://yourdomain.com/install to run the installer" -ForegroundColor Gray
Write-Host "  5. If installer times out, visit /setup.php (file is inside public/)" -ForegroundColor Gray
Write-Host "  6. Uploads use /storage/... — root + public .htaccess include a fallback if storage:link fails (ZIP hosting)" -ForegroundColor Gray
