# Laravel Development Environment Setup Script for Windows
# Run this in PowerShell (Run as Administrator for Chocolatey installs)

$ErrorActionPreference = "Continue"
$requirements = @()

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "  Laravel Dev Environment Check" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# 1. Check PHP (XAMPP)
Write-Host "[1/4] Checking PHP..." -ForegroundColor Yellow
try {
    $phpVersion = php -v 2>$null
    if ($phpVersion) {
        $versionMatch = [regex]::Match($phpVersion, "PHP (\d+\.\d+)")
        if ($versionMatch.Success) {
            $version = [double]$versionMatch.Groups[1].Value
            if ($version -ge 8.2) {
                Write-Host "  OK - PHP $version found (Laravel 12 requires 8.2+)" -ForegroundColor Green
            } else {
                Write-Host "  WARNING - PHP $version found. Laravel 12 requires PHP 8.2+" -ForegroundColor Red
                Write-Host "  Update XAMPP or install PHP 8.2+ from https://windows.php.net/download/" -ForegroundColor Yellow
                $requirements += "PHP 8.2+"
            }
        } else {
            Write-Host "  OK - PHP found" -ForegroundColor Green
        }
    } else {
        throw "Not found"
    }
} catch {
    Write-Host "  MISSING - PHP not in PATH" -ForegroundColor Red
    Write-Host "  Add XAMPP PHP to PATH: C:\xampp\php" -ForegroundColor Yellow
    Write-Host "  Or run: setx PATH `"%PATH%;C:\xampp\php`"" -ForegroundColor Yellow
    $requirements += "PHP 8.2+ (add C:\xampp\php to PATH)"
}

# 2. Check Composer
Write-Host "`n[2/4] Checking Composer..." -ForegroundColor Yellow
try {
    $composerVersion = composer -V 2>$null
    if ($composerVersion) {
        Write-Host "  OK - $composerVersion" -ForegroundColor Green
    } else {
        throw "Not found"
    }
} catch {
    Write-Host "  MISSING - Composer not installed" -ForegroundColor Red
    Write-Host "  Install from: https://getcomposer.org/download/" -ForegroundColor Yellow
    Write-Host "  Or run: winget install Composer.Composer" -ForegroundColor Yellow
    Write-Host "  Or run: choco install composer" -ForegroundColor Yellow
    $requirements += "Composer"
}

# 3. Check Node.js
Write-Host "`n[3/4] Checking Node.js..." -ForegroundColor Yellow
try {
    $nodeVersion = node -v 2>$null
    if ($nodeVersion) {
        Write-Host "  OK - Node $nodeVersion" -ForegroundColor Green
    } else {
        throw "Not found"
    }
} catch {
    Write-Host "  MISSING - Node.js not installed" -ForegroundColor Red
    Write-Host "  Install LTS from: https://nodejs.org/" -ForegroundColor Yellow
    Write-Host "  Or run: winget install OpenJS.NodeJS.LTS" -ForegroundColor Yellow
    Write-Host "  Or run: choco install nodejs-lts" -ForegroundColor Yellow
    $requirements += "Node.js LTS"
}

# 4. Check npm
Write-Host "`n[4/4] Checking npm..." -ForegroundColor Yellow
try {
    $npmVersion = npm -v 2>$null
    if ($npmVersion) {
        Write-Host "  OK - npm $npmVersion" -ForegroundColor Green
    } else {
        throw "Not found"
    }
} catch {
    Write-Host "  MISSING - npm (comes with Node.js)" -ForegroundColor Red
    if (-not ($requirements -contains "Node.js LTS")) {
        $requirements += "Node.js LTS"
    }
}

# Summary
Write-Host "`n========================================" -ForegroundColor Cyan
if ($requirements.Count -eq 0) {
    Write-Host "  All requirements met!" -ForegroundColor Green
    Write-Host "  Run: composer install && npm install" -ForegroundColor Cyan
    Write-Host "  Then: php artisan serve" -ForegroundColor Cyan
} else {
    Write-Host "  Missing: $($requirements -join ', ')" -ForegroundColor Yellow
    Write-Host "  See SETUP-GUIDE.md for installation instructions" -ForegroundColor Yellow
}
Write-Host "========================================`n" -ForegroundColor Cyan
