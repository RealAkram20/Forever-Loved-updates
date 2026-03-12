# Deploy Forever-love to Hostinger - builds and creates deploy zip
# Run from project root: .\deploy-hostinger.ps1

$ErrorActionPreference = "Stop"
$projectRoot = $PSScriptRoot
$zipPath = "c:\xampp\htdocs\Forever-love-deploy.zip"

Write-Host "`n=== Forever-love Hostinger Deploy ===" -ForegroundColor Cyan
Set-Location $projectRoot

# 1. Build frontend assets
Write-Host "`n[1/3] Building Vite assets..." -ForegroundColor Yellow
npm run build
if ($LASTEXITCODE -ne 0) { throw "npm run build failed" }

# 2. Production composer install (optional - uses existing vendor if composer fails)
Write-Host "`n[2/3] Installing production dependencies..." -ForegroundColor Yellow
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

# 3. Create zip (exclude node_modules, .git, etc.)
Write-Host "`n[3/3] Creating deploy zip..." -ForegroundColor Yellow
if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

$include = @(
    "app", "bootstrap", "config", "database", "public", "resources", "routes", "storage", "vendor",
    ".env.example", "artisan", "composer.json", "composer.lock", "package.json"
)

$tempDir = Join-Path $env:TEMP "Forever-love-deploy"
if (Test-Path $tempDir) { Remove-Item $tempDir -Recurse -Force }
New-Item -ItemType Directory -Path $tempDir | Out-Null

foreach ($item in $include) {
    $src = Join-Path $projectRoot $item
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $tempDir -Recurse -Force
    }
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
Write-Host "`nNext: Upload to Hostinger File Manager and follow HOSTINGER-DEPLOY.md" -ForegroundColor Cyan
