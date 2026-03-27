# Build LaraUpdater release package (full application code + built assets)
# Run from project root: .\build-update.ps1
# Creates: public/updates/RELEASE-X.X.X.zip and public/updates/laraupdater.json
#
# Usage:
#   .\build-update.ps1                         # Git diff (only changed paths under allowed roots)
#   .\build-update.ps1 -All                    # Full release — almost entire app (recommended before upload)
#   .\build-update.ps1 -All -Description "..." # Custom description for laraupdater.json
#
# Before -All: run `npm run build` so public/build matches resources/css + resources/js.

param(
    [switch]$All,
    [string]$Description = ""
)

$ErrorActionPreference = "Stop"
$projectRoot = $PSScriptRoot
$versionFile = Join-Path $projectRoot "version.txt"

if (-not (Test-Path $versionFile)) {
    Write-Host "[ERROR] version.txt not found at $versionFile" -ForegroundColor Red
    exit 1
}
$version = (Get-Content $versionFile -Raw).Trim()
$zipName = "RELEASE-$version.zip"
$updatesDir = Join-Path $projectRoot "public\updates"
$zipPath = Join-Path $updatesDir $zipName

# Core directories (always eligible for git-diff matching and for -All)
$updatableDirs = @(
    "app",
    "bootstrap",
    "config",
    "database/migrations",
    "database/seeders",
    "database/factories",
    "resources/css",
    "resources/js",
    "resources/views",
    "routes",
    "public/build",
    "public/images"
)

# Root-level project files (included when present)
$rootFiles = @(
    "artisan",
    "composer.json",
    "composer.lock",
    "package.json",
    "package-lock.json",
    "vite.config.js",
    "postcss.config.js",
    "tailwind.config.js",
    "jsconfig.json",
    "components.json",
    "phpunit.xml",
    ".editorconfig"
)

$alwaysInclude = @(
    "version.txt"
)

function Test-IncludePublicRelativePath {
    param([string]$Relative)
    # User uploads / storage symlink — never ship
    if ($Relative -match '^public/storage(/|$)') { return $false }
    # Do not embed another release zip inside the package
    if ($Relative -match '^public/updates/RELEASE-.+\.zip$') { return $false }
    return $true
}

function Add-PublicTreeFiles {
    param([ref]$FilesList)
    $publicRoot = Join-Path $projectRoot "public"
    if (-not (Test-Path $publicRoot)) { return }
    Get-ChildItem -Path $publicRoot -Recurse -File -ErrorAction SilentlyContinue | ForEach-Object {
        $relative = $_.FullName.Substring($projectRoot.Length + 1) -replace '\\', '/'
        if (Test-IncludePublicRelativePath $relative) {
            if ($relative -notin $FilesList.Value) {
                [void]$FilesList.Value.Add($relative)
            }
        }
    }
}

function Test-FileMatchesUpdatablePrefix {
    param([string]$File)
    foreach ($dir in $updatableDirs) {
        if ($File -eq $dir -or $File.StartsWith("$dir/")) { return $true }
    }
    if ($File.StartsWith("public/") -and (Test-IncludePublicRelativePath $File)) { return $true }
    foreach ($rf in $rootFiles) {
        if ($File -eq $rf) { return $true }
    }
    return $false
}

Write-Host "`n=== Building LaraUpdater package v$version ===" -ForegroundColor Cyan

$filesToInclude = [System.Collections.Generic.List[string]]::new()

if ($All) {
    Write-Host "`nMode: FULL release (app + bootstrap + config + database + resources + routes + public* + root configs)" -ForegroundColor Yellow
    foreach ($dir in $updatableDirs) {
        $fullDir = Join-Path $projectRoot $dir
        if (Test-Path $fullDir) {
            Get-ChildItem -Path $fullDir -Recurse -File | ForEach-Object {
                $relative = $_.FullName.Substring($projectRoot.Length + 1) -replace '\\', '/'
                if ($relative -notin $filesToInclude) { [void]$filesToInclude.Add($relative) }
            }
        }
    }
    Add-PublicTreeFiles ([ref]$filesToInclude)
    foreach ($rf in $rootFiles) {
        $p = Join-Path $projectRoot $rf
        if ((Test-Path $p) -and ($rf -notin $filesToInclude)) { [void]$filesToInclude.Add($rf) }
    }
    # database/* at root (e.g. .gitignore, seed stubs) — never sqlite/sql dumps
    $dbRoot = Join-Path $projectRoot "database"
    if (Test-Path $dbRoot) {
        Get-ChildItem -Path $dbRoot -File -Force | Where-Object { $_.Name -notmatch '\.(sqlite3?|sql)$' } | ForEach-Object {
            $relative = "database/$($_.Name)"
            if ($relative -notin $filesToInclude) { [void]$filesToInclude.Add($relative) }
        }
    }
} else {
    Write-Host "`nMode: Git diff (changed files under allowed roots only)" -ForegroundColor Yellow
    try {
        $gitFiles = git -C $projectRoot diff --name-only HEAD 2>&1
        if ($LASTEXITCODE -ne 0) {
            $gitFiles = git -C $projectRoot diff --name-only --cached 2>&1
        }
        $untrackedFiles = git -C $projectRoot ls-files --others --exclude-standard 2>&1

        $allGitFiles = @()
        if ($gitFiles) { $allGitFiles += $gitFiles }
        if ($untrackedFiles) { $allGitFiles += $untrackedFiles }

        foreach ($file in $allGitFiles) {
            $file = $file.Trim() -replace '\\', '/'
            if ($file -eq "") { continue }
            if ($file -in $alwaysInclude) {
                if ((Test-Path (Join-Path $projectRoot $file)) -and ($file -notin $filesToInclude)) { [void]$filesToInclude.Add($file) }
                continue
            }
            if (-not (Test-FileMatchesUpdatablePrefix $file)) { continue }
            if (-not (Test-Path (Join-Path $projectRoot $file))) { continue }
            if ($file -notin $filesToInclude) { [void]$filesToInclude.Add($file) }
        }
    } catch {
        Write-Host "[WARN] Git not available or not a repo. Use -All flag instead." -ForegroundColor Yellow
        Write-Host "Error: $_" -ForegroundColor Red
        exit 1
    }
}

foreach ($f in $alwaysInclude) {
    if ($f -notin $filesToInclude -and (Test-Path (Join-Path $projectRoot $f))) {
        [void]$filesToInclude.Add($f)
    }
}

$migrationsDir = Join-Path $projectRoot "database/migrations"
if (Test-Path $migrationsDir) {
    Get-ChildItem -Path $migrationsDir -File -Filter "*.php" | ForEach-Object {
        $relative = "database/migrations/$($_.Name)"
        if ($relative -notin $filesToInclude) { [void]$filesToInclude.Add($relative) }
    }
}

$filesToInclude = $filesToInclude | Select-Object -Unique | Sort-Object

# Generated per machine — do not overwrite production (composer / package:discover rebuilds these)
$filesToInclude = $filesToInclude | Where-Object {
    if ($_ -match '^bootstrap/cache/' -and $_ -notmatch '\.gitignore$') { return $false }
    return $true
}

if ($filesToInclude.Count -eq 0) {
    Write-Host "`n[INFO] No changed files found. Nothing to package." -ForegroundColor Yellow
    Write-Host "  Use -All for a full release zip." -ForegroundColor Gray
    exit 0
}

Write-Host "`nFiles to include: $($filesToInclude.Count)" -ForegroundColor Cyan

if (-not (Test-Path $updatesDir)) {
    New-Item -ItemType Directory -Path $updatesDir | Out-Null
}

# Avoid names like "...-1.1.5" — on Windows, Resolve-Path vs FullName length can mismatch and
# corrupt zip entry names (everything lands under a bogus "-1.1.5/" folder on the server).
$versionSafe = ($version -replace '\.', '_')
$tempDir = Join-Path $env:TEMP "ForeverLoveUpdate_v$versionSafe"
if (Test-Path -LiteralPath $tempDir) { Remove-Item -LiteralPath $tempDir -Recurse -Force }
New-Item -ItemType Directory -Path $tempDir -Force | Out-Null

foreach ($f in $filesToInclude) {
    $src = Join-Path $projectRoot $f
    if (Test-Path $src) {
        $parent = Split-Path $f -Parent
        if ($parent) {
            $destDir = Join-Path $tempDir $parent
            if (-not (Test-Path $destDir)) {
                New-Item -ItemType Directory -Path $destDir -Force | Out-Null
            }
        }
        Copy-Item -Path $src -Destination (Join-Path $tempDir $f) -Force
        Write-Host "  + $f" -ForegroundColor Gray
    } else {
        Write-Host "  ! Skip (not found): $f" -ForegroundColor Yellow
    }
}

if ($Description -eq "") {
    $Description = "Full application update to version $version (run npm run build before packaging; composer install on server if composer.json changed)."
}

$json = @{
    version = $version
    archive = $zipName
    description = $Description
} | ConvertTo-Json

# Same manifest inside the zip (tree copy can be stale vs this release)
$manifestInTemp = Join-Path $tempDir "public\updates\laraupdater.json"
$manifestParent = Split-Path $manifestInTemp -Parent
if (-not (Test-Path $manifestParent)) {
    New-Item -ItemType Directory -Path $manifestParent -Force | Out-Null
}
$json | Set-Content -Path $manifestInTemp -Encoding UTF8

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Add-Type -AssemblyName System.IO.Compression.FileSystem
# Do NOT use Resolve-Path for the root: on Windows it can return 8.3 (RIOAKR~1) while
# Get-ChildItem .FullName uses the long path — Substring/StartsWith then break and zip entries
# become "-1.1.5/..." under public_html. Use the same FileSystem API as the child files.
$tempDirResolved = (Get-Item -LiteralPath $tempDir).FullName.TrimEnd('\')
function Get-RelativePathUnderRoot {
    param([string]$Root, [string]$FullPath)
    $FullPath = $FullPath.TrimEnd('\')
    if ($FullPath.Length -lt $Root.Length) { return $null }
    if (-not $FullPath.StartsWith($Root, [StringComparison]::OrdinalIgnoreCase)) { return $null }
    $rest = $FullPath.Substring($Root.Length).TrimStart('\')
    if ($rest -eq '') { return $null }
    return ($rest -replace '\\', '/')
}
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
try {
    Get-ChildItem -LiteralPath $tempDir -Recurse -File | ForEach-Object {
        $relativePath = Get-RelativePathUnderRoot -Root $tempDirResolved -FullPath $_.FullName
        if (-not $relativePath) {
            Write-Host "  ! Skip zip entry (path outside temp): $($_.FullName)" -ForegroundColor Yellow
            return
        }
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $relativePath, 'Optimal') | Out-Null
    }
} finally { $zip.Dispose() }
Remove-Item $tempDir -Recurse -Force

$jsonPath = Join-Path $updatesDir "laraupdater.json"
$json | Set-Content -Path $jsonPath -Encoding UTF8

Write-Host "`n=== Done ===" -ForegroundColor Green
Write-Host "Output: $updatesDir" -ForegroundColor Green
Write-Host "  - $zipName ($($filesToInclude.Count) files)" -ForegroundColor Green
Write-Host "  - laraupdater.json" -ForegroundColor Green
Write-Host "`nExcluded from zip: vendor/, node_modules/, storage/*, public/storage/, public/updates/RELEASE-*.zip, .env" -ForegroundColor DarkGray
Write-Host "After install on server: if composer.json changed, run  composer install --no-dev --optimize-autoloader" -ForegroundColor DarkYellow
