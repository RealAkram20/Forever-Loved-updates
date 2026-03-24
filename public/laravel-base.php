<?php

/**
 * Resolves Laravel project root when the web server document root is not guaranteed
 * to be the public/ folder (shared hosting: full app under public_html, or app in a sibling folder).
 */
declare(strict_types=1);

/**
 * @return non-empty-string
 */
function forever_loved_laravel_base(): string
{
    $publicDir = __DIR__;
    $parent = dirname($publicDir);

    $candidates = [
        $parent,
        $publicDir,
        $parent.'/private',
        $parent.'/laravel',
        $parent.'/application',
    ];

    foreach ($candidates as $base) {
        if (is_file($base.'/vendor/autoload.php') && is_file($base.'/bootstrap/app.php')) {
            return $base;
        }
    }

    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    $checked = array_map(static fn (string $b): string => $b.'/vendor/autoload.php', $candidates);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Missing Laravel files</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:42rem;margin:2rem auto;padding:0 1rem;line-height:1.5}code{background:#eee;padding:.1rem .35rem;border-radius:4px}</style>';
    echo '</head><body><h1>Composer <code>vendor</code> folder not found</h1>';
    echo '<p>The full application must be on the server. <code>vendor/autoload.php</code> was not found in any of these locations:</p><ul>';
    foreach ($checked as $p) {
        echo '<li><code>'.htmlspecialchars($p, ENT_QUOTES, 'UTF-8').'</code></li>';
    }
    echo '</ul>';
    echo '<p><strong>Fix:</strong> Re-upload the complete deploy zip (including the large <code>vendor</code> folder at the same level as <code>app</code> and <code>public</code>). ';
    echo 'If the upload was partial, increase FTP timeout or upload <code>vendor</code> as a separate archive. ';
    echo 'Do not upload only the contents of <code>public/</code>.</p>';
    echo '</body></html>';

    exit(1);
}
