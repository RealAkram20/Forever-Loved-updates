<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use pcinaglia\laraUpdater\LaraUpdaterController as BaseLaraUpdaterController;

/**
 * Fully overridden LaraUpdater controller.
 * Bypasses the vendor's update() entirely to fix:
 *  - Version comparison (uses version_compare instead of string <=)
 *  - Zip cleanup (deletes the actual .zip, not a mangled path)
 *  - Auto-runs migrations after file extraction
 *  - Uses local/remote fallback for version info
 *  - Clears caches after update
 *  - Backup kept until success; recovery on extract/migrate failure
 */
class LaraUpdaterController extends BaseLaraUpdaterController
{
    private string $responseHtml = '';

    private ?string $backupDir = null;

    // ─── Check ──────────────────────────────────────────────────────────

    public function check(): JsonResponse
    {
        try {
            $currentVersion = $this->getCurrentVersion();
            $lastVersion = $this->getLastVersionInfo();

            if ($lastVersion && version_compare($lastVersion['version'], $currentVersion, '>')) {
                return response()->json($lastVersion);
            }

            return response()->json([]);
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }

    // ─── Update (fully overridden, vendor method is not called) ─────────

    public function update(): Response
    {
        $this->appendLog('Current version: '.$this->getCurrentVersion());

        if (! $this->hasPermission()) {
            $this->appendLog('Permission denied.', 'warn');

            return $this->htmlResponse();
        }

        $lastVersionInfo = $this->getLastVersionInfo();

        if (! $lastVersionInfo) {
            $this->appendLog('Could not reach update server.', 'err');

            return $this->htmlResponse();
        }

        if (! version_compare($lastVersionInfo['version'], $this->getCurrentVersion(), '>')) {
            $this->appendLog('Already up to date.');

            return $this->htmlResponse();
        }

        try {
            $zipPath = $this->downloadZip($lastVersionInfo['archive']);
            if ($zipPath === false) {
                return $this->htmlResponse();
            }

            Artisan::call('down');
            $this->appendLog('Maintenance mode ON.');

            if (! $this->extractAndInstall($zipPath)) {
                $this->appendLog('Installation failed.', 'err');
                $this->runRecovery();
                Artisan::call('up');

                return $this->htmlResponse();
            }

            if (! $this->runMigrations()) {
                $this->appendLog('Migrations failed; restoring files from backup.', 'err');
                $this->runRecovery();
                Artisan::call('up');
                $this->appendLog('Maintenance mode OFF.');

                return $this->htmlResponse();
            }

            File::put(base_path('version.txt'), $lastVersionInfo['version']);
            $this->appendLog('Version updated to '.$lastVersionInfo['version'].'.');

            $this->clearCaches();

            Artisan::call('up');
            $this->appendLog('Maintenance mode OFF.');
            $this->appendLog('Update installed successfully.');
            $this->discardBackupDir();

        } catch (\Exception $e) {
            $this->appendLog('Exception: '.$e->getMessage(), 'err');
            $this->runRecovery();

            try {
                Artisan::call('up');
            } catch (\Exception $ex) {
                // Already up or can't bring up
            }
        }

        return $this->htmlResponse();
    }

    // ─── getCurrentVersion ──────────────────────────────────────────────

    public function getCurrentVersion(): string
    {
        $path = base_path('version.txt');

        if (! file_exists($path)) {
            return '0.0.0';
        }

        return trim((string) file_get_contents($path));
    }

    // ─── Internals ──────────────────────────────────────────────────────

    private function getLastVersionInfo(): ?array
    {
        $localPath = public_path('updates/laraupdater.json');

        if (file_exists($localPath)) {
            $data = json_decode(file_get_contents($localPath), true);
            if (is_array($data) && ! empty($data['version'])) {
                return $data;
            }
        }

        $baseUrl = config('laraupdater.update_baseurl');
        if (empty($baseUrl)) {
            return null;
        }

        try {
            $json = @file_get_contents(rtrim($baseUrl, '/').'/laraupdater.json');
            if ($json === false) {
                return null;
            }
            $data = json_decode($json, true);

            return (is_array($data) && ! empty($data['version'])) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function downloadZip(string $filename): string|false
    {
        $this->appendLog('Downloading update...');

        $tmpDir = base_path(config('laraupdater.tmp_folder_name', 'tmp'));
        File::ensureDirectoryExists($tmpDir, 0755);

        $localFile = $tmpDir.'/'.$filename;
        $remoteUrl = rtrim((string) config('laraupdater.update_baseurl'), '/').'/'.$filename;

        $this->appendLog('GET '.$remoteUrl);

        try {
            $response = Http::timeout(300)->sink($localFile)->get($remoteUrl);

            if (! $response->successful()) {
                if (File::exists($localFile)) {
                    File::delete($localFile);
                }
                $this->appendLog(
                    'Download failed: HTTP '.$response->status().'. Ensure laraupdater.json "archive" matches the zip filename on the server, and that /updates/.htaccess does not block .zip files.',
                    'err'
                );
                $this->appendLog('Tried: '.$remoteUrl, 'err');

                return false;
            }

            if (! File::exists($localFile) || File::size($localFile) === 0) {
                $this->appendLog('Download failed: empty file. URL: '.$remoteUrl, 'err');

                return false;
            }
        } catch (\Throwable $e) {
            if (File::exists($localFile)) {
                File::delete($localFile);
            }
            $this->appendLog('Download failed: '.$e->getMessage(), 'err');
            $this->appendLog('URL: '.$remoteUrl, 'err');

            return false;
        }

        $this->appendLog('Download complete.');

        return $localFile;
    }

    private function extractAndInstall(string $zipPath): bool
    {
        try {
            $upgradeScript = null;
            $tmpDir = base_path(config('laraupdater.tmp_folder_name', 'tmp'));
            $upgradeScriptPath = $tmpDir.'/'.config('laraupdater.script_filename', 'upgrade.php');

            $zip = new \ZipArchive;
            if ($zip->open($zipPath) !== true) {
                $this->appendLog('Failed to open zip archive.', 'err');

                return false;
            }

            $this->appendLog('Extracting files...');

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                if (str_ends_with($entry, '/')) {
                    continue;
                }

                $relativePath = $this->normalizeZipEntryPath($entry);
                if ($relativePath === null) {
                    continue;
                }

                $contents = $zip->getFromIndex($i);
                $contents = str_replace("\r\n", "\n", $contents);

                if (basename($relativePath) === config('laraupdater.script_filename', 'upgrade.php')) {
                    File::put($upgradeScriptPath, $contents);
                    $upgradeScript = $upgradeScriptPath;
                    continue;
                }

                $destDir = dirname(base_path($relativePath));
                File::ensureDirectoryExists($destDir, 0755);

                if (File::exists(base_path($relativePath))) {
                    $this->backupFile($relativePath);
                }

                File::put(base_path($relativePath), $contents);
                $this->appendLog('Updated: '.$relativePath);
            }

            $zip->close();

            if ($upgradeScript && file_exists($upgradeScript)) {
                require_once $upgradeScript;
                if (function_exists('main')) {
                    main();
                    $this->appendLog('Executed upgrade.php main().');
                }
                @unlink($upgradeScript);
            }

            // Clean up the actual zip file (backup dir kept until migrate + version succeed)
            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }

            $this->appendLog('File extraction complete.');

            return true;

        } catch (\Exception $e) {
            $this->appendLog('Extraction error: '.$e->getMessage(), 'err');

            return false;
        }
    }

    /**
     * Normalize zip entry paths: strip top-level folder wrapper(s) if present
     * (e.g. "RELEASE-1.0.1/app/Models/Foo.php" -> "app/Models/Foo.php").
     * Strips legacy Windows zip bugs ("-1.1.5/app/..."), nested wrappers, and
     * common hosting folder names when used as a single outer directory.
     */
    private function normalizeZipEntryPath(string $entry): ?string
    {
        $entry = str_replace('\\', '/', $entry);
        $entry = ltrim($entry, '/');
        $parts = array_values(array_filter(explode('/', $entry), fn ($p) => $p !== '' && $p !== '.'));

        if ($parts === []) {
            return null;
        }

        $maxStrip = 20;
        for ($n = 0; $n < $maxStrip && count($parts) >= 2; $n++) {
            $first = $parts[0];
            if (! $this->isZipPathWrapperSegment($first)) {
                break;
            }
            array_shift($parts);
        }

        if ($parts === []) {
            return null;
        }

        $path = implode('/', $parts);

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $realBase = realpath(base_path());
        $resolvedTarget = realpath(dirname(base_path($path)));
        if ($resolvedTarget !== false && ! str_starts_with($resolvedTarget, $realBase)) {
            return null;
        }

        return $path;
    }

    /**
     * True if this path segment is only a container added when building the zip,
     * not part of the Laravel app tree (app/, public/, config/, …).
     */
    private function isZipPathWrapperSegment(string $segment): bool
    {
        $segment = trim($segment);
        if ($segment === '' || $segment === '.') {
            return true;
        }

        $lower = strtolower($segment);

        if ($lower === '__macosx' || str_starts_with($segment, '__MACOSX')) {
            return true;
        }

        if (str_starts_with($lower, 'release')) {
            return true;
        }

        if (str_starts_with($segment, '__')
            || str_starts_with($segment, 'Forever-love-update')
            || str_starts_with($segment, 'ForeverLoveUpdate_v')) {
            return true;
        }

        // Broken Windows zip entries: "-1.1.5", "-1.1.0", optional suffix
        if (preg_match('/^-\d+\.\d+\.\d+(?:[.\-+a-zA-Z0-9]*)?$/', $segment) === 1) {
            return true;
        }

        // Some tools use a bare semver folder at the zip root
        if (preg_match('/^\d+\.\d+\.\d+$/', $segment) === 1) {
            return true;
        }

        return in_array($lower, ['public_html', 'htdocs'], true);
    }

    private function backupFile(string $relativePath): void
    {
        if (! $this->backupDir) {
            $this->backupDir = base_path('backup_'.date('Ymd'));
        }

        $dest = $this->backupDir.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($dest), 0755);
        File::copy(base_path($relativePath), $dest);
    }

    private function runMigrations(): bool
    {
        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());
            if ($output) {
                $this->appendLog('Migrations: '.$output);
            } else {
                $this->appendLog('Migrations: nothing to migrate.');
            }

            return $exitCode === 0;
        } catch (\Exception $e) {
            $this->appendLog('Migration error: '.$e->getMessage(), 'err');

            return false;
        }
    }

    private function clearCaches(): void
    {
        try {
            Artisan::call('optimize:clear');
            $out = trim(Artisan::output());
            if ($out !== '') {
                $this->appendLog('optimize:clear: '.$out);
            } else {
                $this->appendLog('Application caches cleared (optimize:clear).');
            }
        } catch (\Exception $e) {
            $this->appendLog('optimize:clear warning: '.$e->getMessage(), 'warn');
            try {
                Artisan::call('config:clear');
                Artisan::call('route:clear');
                Artisan::call('view:clear');
            } catch (\Exception $e2) {
                $this->appendLog('Fallback cache clear failed: '.$e2->getMessage(), 'warn');
            }
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $this->appendLog('OPcache reset (if enabled).');
        }
    }

    private function discardBackupDir(): void
    {
        if ($this->backupDir && File::isDirectory($this->backupDir)) {
            File::deleteDirectory($this->backupDir);
            $this->appendLog('Backup directory removed after successful update.');
        }
        $this->backupDir = null;
    }

    private function runRecovery(): void
    {
        $this->appendLog('Attempting recovery from backup...');

        if (! $this->backupDir || ! File::isDirectory($this->backupDir)) {
            $this->appendLog('No backup found for recovery.', 'warn');

            return;
        }

        try {
            foreach (File::allFiles($this->backupDir) as $file) {
                $relative = str_replace(
                    rtrim($this->backupDir, '/\\').'/',
                    '',
                    str_replace('\\', '/', $file->getPathname())
                );
                File::copy($file->getPathname(), base_path($relative));
            }
            $this->appendLog('Recovery complete.');
        } catch (\Exception $e) {
            $this->appendLog('Recovery failed: '.$e->getMessage(), 'err');
        }
    }

    private function hasPermission(): bool
    {
        $allowed = config('laraupdater.allow_users_id');
        if ($allowed === false || $allowed === null) {
            return true;
        }

        return is_array($allowed) && in_array(Auth::id(), $allowed);
    }

    private function appendLog(string $msg, string $type = 'info'): void
    {
        $this->responseHtml .= $msg.'<BR>';
        $prefix = 'LaraUpdater - ';

        match ($type) {
            'warn' => Log::warning($prefix.$msg),
            'err' => Log::error($prefix.$msg),
            default => Log::info($prefix.$msg),
        };
    }

    private function htmlResponse(): Response
    {
        return response($this->responseHtml, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
