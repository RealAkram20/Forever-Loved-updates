<?php
/**
 * Forever Love - Server Setup Script
 * Place with the rest of public/ (e.g. public_html/public/setup.php if the full app lives under public_html).
 * DELETE THIS FILE immediately after installation completes!
 */

set_time_limit(300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

ob_implicit_flush(true);
if (ob_get_level()) ob_end_flush();

require __DIR__.'/laravel-base.php';
$basePath = forever_loved_laravel_base();

echo "<!DOCTYPE html><html><head><title>Setup</title><style>body{font-family:monospace;background:#1a1a2e;color:#e0e0e0;padding:2rem;line-height:1.8}
.ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}h1{color:#818cf8}</style></head><body>";
echo "<h1>Forever Love - Server Setup</h1><hr><br>";

function step($msg) { echo "<b class='ok'>[OK]</b> $msg<br>"; flush(); }
function fail($msg) { echo "<b class='err'>[FAIL]</b> $msg<br>"; flush(); }
function warn($msg) { echo "<b class='warn'>[WARN]</b> $msg<br>"; flush(); }
function info($msg) { echo "$msg<br>"; flush(); }

// ── Boot Laravel ──
info("Booting Laravel...");
require $basePath.'/vendor/autoload.php';
$app = require_once $basePath.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
step("Laravel booted.");

// ── Step 1: Key generate ──
info("<br><b>Step 1/6: Generating app key...</b>");
try {
    Illuminate\Support\Facades\Artisan::call('key:generate', ['--force' => true]);
    step("App key generated.");
} catch (Exception $e) {
    warn("Key generate note: " . $e->getMessage());
}

// ── Step 2: Migrations ──
info("<br><b>Step 2/6: Running database migrations...</b>");
try {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $output = trim(Illuminate\Support\Facades\Artisan::output());
    if ($output) {
        info("<pre style='color:#94a3b8;margin:0.5rem 0'>$output</pre>");
    }
    step("Migrations complete.");
} catch (Exception $e) {
    fail("Migration error: " . $e->getMessage());
    echo "</body></html>";
    exit;
}

// ── Step 3: Seed roles ──
info("<br><b>Step 3/6: Seeding roles and permissions...</b>");
try {
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder', '--force' => true]);
    step("Roles seeded.");
} catch (Exception $e) {
    fail("Role seed error: " . $e->getMessage());
}

// ── Step 4: Seed subscription plans ──
info("<br><b>Step 4/6: Seeding subscription plans...</b>");
try {
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\SubscriptionPlanSeeder', '--force' => true]);
    step("Subscription plans seeded.");
} catch (Exception $e) {
    fail("Plan seed error: " . $e->getMessage());
}

// ── Step 5: Create admin ──
info("<br><b>Step 5/6: Creating admin account...</b>");
$adminName  = $_GET['name']  ?? 'Admin';
$adminEmail = $_GET['email'] ?? '';
$adminPass  = $_GET['pass']  ?? '';

if (empty($adminEmail) || empty($adminPass)) {
    warn("No admin credentials provided. Re-run with:");
    info("<code style='color:#818cf8'>setup.php?name=YourName&email=you@email.com&pass=YourPassword</code>");
} else {
    try {
        $user = App\Models\User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Illuminate\Support\Facades\Hash::make($adminPass),
            ]
        );
        $user->assignRole('super-admin');
        step("Admin created: $adminEmail");
    } catch (Exception $e) {
        fail("Admin error: " . $e->getMessage());
    }
}

// ── Step 6: Storage link + finalize ──
info("<br><b>Step 6/6: Finalizing...</b>");
try {
    Illuminate\Support\Facades\Artisan::call('storage:link');
    step("Storage link created.");
} catch (Exception $e) {
    warn("Storage link: " . $e->getMessage());
}

try {
    Illuminate\Support\Facades\File::put(storage_path('installed'), json_encode([
        'installed_at' => now()->toIso8601String(),
        'version' => trim(Illuminate\Support\Facades\File::get(base_path('version.txt'))),
    ]));
    step("Installation lock created.");
} catch (Exception $e) {
    fail("Lock file: " . $e->getMessage());
}

// ── Done ──
echo "<br><hr>";
echo "<h1 class='ok'>Installation Complete!</h1>";
echo "<p>Login at: <a href='" . config('app.url') . "/login' style='color:#818cf8'>" . config('app.url') . "/login</a></p>";
echo "<p class='err'><b>⚠ DELETE THIS FILE (public/setup.php) IMMEDIATELY!</b></p>";
echo "</body></html>";
