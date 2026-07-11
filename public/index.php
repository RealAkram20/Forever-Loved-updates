<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Serving From A Sub-Directory
|--------------------------------------------------------------------------
|
| Under XAMPP the app lives at http://localhost/<project> and the root
| .htaccess rewrites requests into public/. That leaves SCRIPT_NAME as
| /<project>/public/index.php while REQUEST_URI stays /<project>/..., and
| Symfony finds no common prefix between the two, so it blanks the base URL
| and every route 404s. Re-point the script at the directory the browser
| actually asked for. A vhost rooted at public/ never enters this branch.
|
*/

$script = $_SERVER['SCRIPT_NAME'] ?? '';

if (str_ends_with($script, '/public/index.php')
    && ! str_starts_with($_SERVER['REQUEST_URI'] ?? '', dirname($script).'/')) {
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = dirname($script, 2).'/index.php';
}

require __DIR__.'/laravel-base.php';
$basePath = forever_loved_laravel_base();

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $basePath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
