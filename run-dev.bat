@echo off
REM Use PHP 8.2 for Laravel (winget install)
set "PHP82=C:\Users\Rio Akram\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe"
set "PATH=%PHP82%;%PATH%"

cd /d "%~dp0"

echo Starting Laravel development server...
php artisan serve
