<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PDO;
use PDOException;

class InstallController extends Controller
{
    // ─── Step 1: Requirements ───────────────────────────────────────────

    public function requirements()
    {
        $checks = $this->runRequirementChecks();
        $allPassed = collect($checks['php'])->every(fn ($c) => $c['pass'])
            && collect($checks['extensions'])->every(fn ($c) => $c['pass'])
            && collect($checks['writable'])->every(fn ($c) => $c['pass']);

        return view('pages.install.requirements', compact('checks', 'allPassed'))
            ->with('currentStep', 'requirements');
    }

    private function runRequirementChecks(): array
    {
        return [
            'php' => [
                ['label' => 'PHP >= 8.2', 'pass' => version_compare(PHP_VERSION, '8.2.0', '>='), 'value' => PHP_VERSION],
            ],
            'extensions' => collect([
                'pdo_mysql', 'pdo_sqlite', 'mbstring', 'openssl',
                'fileinfo', 'curl', 'tokenizer', 'xml', 'ctype', 'json',
            ])->map(fn ($ext) => [
                'label' => $ext,
                'pass' => extension_loaded($ext),
            ])->push([
                'label' => 'bcmath or gmp',
                'pass' => extension_loaded('bcmath') || extension_loaded('gmp'),
            ])->all(),
            'writable' => collect([
                'storage' => storage_path(),
                'storage/framework' => storage_path('framework'),
                'storage/logs' => storage_path('logs'),
                'bootstrap/cache' => base_path('bootstrap/cache'),
                'database/geo' => database_path('geo'),
            ])->map(fn ($path, $label) => [
                'label' => $label,
                'pass' => is_writable($path),
                'value' => $path,
            ])->push([
                'label' => '.env writable',
                'pass' => is_writable(base_path('.env')) || is_writable(base_path()),
            ])->values()->all(),
        ];
    }

    // ─── Step 2: Database ───────────────────────────────────────────────

    public function database()
    {
        $db = session('install.database', [
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'forever_love',
            'username' => 'root',
            'password' => '',
        ]);

        return view('pages.install.database', compact('db'))
            ->with('currentStep', 'database');
    }

    public function validateDatabase(Request $request)
    {
        $request->validate([
            'host' => 'required|string',
            'port' => 'required|numeric',
            'database' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/'],
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        try {
            $dsn = "mysql:host={$request->host};port={$request->port}";
            $pdo = new PDO($dsn, $request->username, $request->password ?? '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $request->database);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $pdo->exec("USE `{$dbName}`");

            return response()->json(['success' => true, 'message' => 'Connection successful. Database is ready.']);
        } catch (PDOException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function storeDatabase(Request $request)
    {
        $validated = $request->validate([
            'host' => 'required|string',
            'port' => 'required|numeric',
            'database' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/'],
            'username' => 'required|string',
            'password' => 'nullable|string',
        ]);

        try {
            $dsn = "mysql:host={$validated['host']};port={$validated['port']}";
            $pdo = new PDO($dsn, $validated['username'], $validated['password'] ?? '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $validated['database']);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            return back()->withInput()->withErrors(['database' => 'Database connection failed: '.$e->getMessage()]);
        }

        session(['install.database' => $validated]);

        return redirect()->route('install.settings');
    }

    // ─── Step 3: App Settings ───────────────────────────────────────────

    public function appSettings(Request $request)
    {
        $settings = session('install.settings', [
            'app_name' => 'Forever Love',
            'app_url' => $request->getSchemeAndHttpHost(),
            'app_env' => 'production',
        ]);

        return view('pages.install.app-settings', compact('settings'))
            ->with('currentStep', 'settings');
    }

    public function storeAppSettings(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:100',
            'app_url' => 'required|url|max:255',
            'app_env' => 'required|in:local,production',
        ]);

        session(['install.settings' => $validated]);

        return redirect()->route('install.admin');
    }

    // ─── Step 4: Admin Account ──────────────────────────────────────────

    public function adminAccount()
    {
        $admin = session('install.admin', [
            'name' => '',
            'email' => '',
        ]);

        return view('pages.install.admin', compact('admin'))
            ->with('currentStep', 'admin');
    }

    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        session(['install.admin' => [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]]);

        return redirect()->route('install.run');
    }

    // ─── Step 5: Run Installation ───────────────────────────────────────

    private function installDataPath(): string
    {
        return storage_path('app/install-data.json');
    }

    private function saveInstallData(array $data): void
    {
        $dir = dirname($this->installDataPath());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        File::put($this->installDataPath(), json_encode($data));
    }

    private function loadInstallData(): ?array
    {
        $path = $this->installDataPath();
        if (! file_exists($path)) {
            return null;
        }
        $data = json_decode(File::get($path), true);
        return is_array($data) ? $data : null;
    }

    public function run()
    {
        $db = session('install.database');
        $settings = session('install.settings');
        $admin = session('install.admin');

        if ($db && $settings && $admin) {
            $this->saveInstallData([
                'database' => $db,
                'settings' => $settings,
                'admin' => $admin,
                'last_completed_step' => 0,
            ]);
            session()->forget(['install.database', 'install.settings', 'install.admin']);
        } else {
            $existing = $this->loadInstallData();
            if (! $existing || ! isset($existing['database'], $existing['settings'], $existing['admin'])) {
                return redirect()->route('install.requirements')
                    ->with('error', 'Please complete all previous steps first.');
            }
        }

        $snapshot = $this->loadInstallData();
        $lastCompleted = (int) ($snapshot['last_completed_step'] ?? 0);

        return view('pages.install.progress')
            ->with('currentStep', 'install')
            ->with('installLastCompletedStep', $lastCompleted);
    }

    public function executeStep(Request $request, int $step)
    {
        $data = $this->loadInstallData();

        if (! $data || ! isset($data['database'], $data['settings'], $data['admin'])) {
            return response()->json(['success' => false, 'message' => 'Installation data not found. Please restart the installer.'], 422);
        }

        $db = $data['database'];
        $settings = $data['settings'];
        $admin = $data['admin'];

        try {
            match ($step) {
                1 => $this->stepWriteEnv($db, $settings),
                2 => $this->stepReloadAndGenerateKey($db, $settings),
                3 => $this->stepMigrate(),
                4 => $this->stepSeed(),
                5 => $this->stepCreateAdmin($admin),
                6 => $this->stepStorageLink(),
                7 => $this->stepOptimize(),
                8 => $this->stepFinalize(),
                default => throw new \InvalidArgumentException('Invalid step.'),
            };

            if ($step === 8) {
                @unlink($this->installDataPath());
            } else {
                $this->persistLastCompletedStep($step);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function stepWriteEnv(array $db, array $settings): void
    {
        $this->writeEnvFile($db, $settings);
    }

    private function stepReloadAndGenerateKey(array $db, array $settings): void
    {
        $this->reloadConfig($db, $settings);
        Artisan::call('key:generate', ['--force' => true]);
    }

    private function persistLastCompletedStep(int $step): void
    {
        $data = $this->loadInstallData();
        if (! is_array($data)) {
            return;
        }
        $prev = (int) ($data['last_completed_step'] ?? 0);
        if ($step > $prev) {
            $data['last_completed_step'] = $step;
            $this->saveInstallData($data);
        }
    }

    private function stepMigrate(): void
    {
        set_time_limit(120);
        Artisan::call('migrate', ['--force' => true]);
    }

    private function stepSeed(): void
    {
        set_time_limit(120);
        Artisan::call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => SubscriptionPlanSeeder::class, '--force' => true]);
        Artisan::call('db:seed', ['--class' => PageSeeder::class, '--force' => true]);
    }

    private function stepCreateAdmin(array $admin): void
    {
        $user = User::firstOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'password' => Hash::make($admin['password']),
            ]
        );
        $user->assignRole('super-admin');
    }

    private function stepStorageLink(): void
    {
        try {
            Artisan::call('storage:link');
        } catch (\Exception $e) {
            // Link may already exist
        }
    }

    private function stepOptimize(): void
    {
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
    }

    private function stepFinalize(): void
    {
        $version = file_exists(base_path('version.txt'))
            ? trim(File::get(base_path('version.txt')))
            : '1.0.0';

        File::put(storage_path('installed'), json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => $version,
        ]));
    }

    private function writeEnvFile(array $db, array $settings): void
    {
        $isProduction = ($settings['app_env'] ?? 'production') === 'production';

        $currentKey = config('app.key') ?: 'base64:'.base64_encode(random_bytes(32));

        $env = <<<ENV
APP_NAME="{$settings['app_name']}"
APP_ENV={$settings['app_env']}
APP_KEY={$currentKey}
APP_DEBUG={$this->bool(! $isProduction)}
APP_URL={$settings['app_url']}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL={$this->logLevel($isProduction)}

DB_CONNECTION=mysql
DB_HOST={$db['host']}
DB_PORT={$db['port']}
DB_DATABASE={$db['database']}
DB_USERNAME={$db['username']}
DB_PASSWORD="{$this->escapeEnvValue($db['password'] ?? '')}"

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT={$this->bool($isProduction)}
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE={$this->bool($isProduction)}

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=database

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

VITE_APP_NAME="\${APP_NAME}"

PESAPAL_VERIFY_SSL={$this->bool($isProduction)}
ENV;

        File::put(base_path('.env'), $env);
    }

    private function reloadConfig(array $db, array $settings): void
    {
        config([
            'app.name' => $settings['app_name'],
            'app.env' => $settings['app_env'],
            'app.url' => $settings['app_url'],
            'database.connections.mysql.host' => $db['host'],
            'database.connections.mysql.port' => $db['port'],
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'],
            'database.connections.mysql.password' => $db['password'] ?? '',
        ]);

        app('db')->purge('mysql');
    }

    private function logLevel(bool $isProduction): string
    {
        return $isProduction ? 'error' : 'debug';
    }

    private function escapeEnvValue(string $value): string
    {
        return addcslashes($value, '"\\$');
    }

    private function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    // ─── Complete ───────────────────────────────────────────────────────

    public function complete()
    {
        return view('pages.install.complete')
            ->with('currentStep', 'install');
    }
}
