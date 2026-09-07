<?php
/**
 * Manual re-run of the platform homepage showcase copy.
 *
 * The copy and the update both live in App\Support\PlatformShowcaseCopy, and a data migration
 * applies it on deploy. This file exists for the case where somebody needs to push it by hand
 * (a restored backup, a page re-saved from an old builder session) and is a one-liner so it
 * can never disagree with the migration.
 *
 *     php artisan tinker --execute="require 'database/scripts/showcase-copy.php';"
 *
 * Idempotent — a second run reports "already current".
 */
$touched = App\Support\PlatformShowcaseCopy::apply();

echo $touched ? "Updated {$touched} store(s)." : 'Already current.', PHP_EOL;
