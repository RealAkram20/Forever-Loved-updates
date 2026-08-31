#!/bin/sh
#
# Runs once per container start, before nginx and PHP-FPM come up.
set -e

cd /var/www/html

echo "[entrypoint] preparing storage"

# storage/ is a persistent volume, so on a brand-new one these do not exist yet and
# Laravel will fail on its first write. Creating them here rather than baking them
# into the image is the only thing that survives the mount.
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

# public/storage → storage/app/public. Re-linked every boot because public/ lives in
# the image and is replaced on each deploy, while the volume behind it persists.
php artisan storage:link --force >/dev/null 2>&1 || true

echo "[entrypoint] running migrations"

# Deliberately fatal. A container serving the app against a database that has not
# been migrated will fail in ways that look like data loss to a family. Coolify keeps
# the previous container serving until this one reports healthy, so a failed
# migration costs a deploy rather than the site.
php artisan migrate --force

echo "[entrypoint] syncing the theme catalogue"

# A template is only selectable once a themes row points at it, and until now the only
# thing creating those rows was 2026_08_26_100002_seed_theme_catalogue. That migration
# calls ThemeCatalogue::sync() and calls itself "idempotent, so adding a template later
# costs nothing" — but a migration runs once. It ran the day it shipped and will never
# run again, so every template added *after* it lands on the server with its blades,
# its manifest and its artwork, and no row. A-Plus deployed exactly that way: the files
# were live and served, and the gallery did not list it.
#
# Here instead, because this is the thing that actually runs on every deploy. The
# command is safe to repeat by design — it fills in what is missing and rewrites the
# name, description and tokens of the rows it already has from the manifest.
#
# Not fatal. A stale catalogue means a template cannot be chosen yet; it does not stop a
# single site rendering, because sites resolve their template through a row that already
# exists. That is not worth failing a deploy over, unlike a missed migration. The output
# is left visible so a failure is legible in the deploy log rather than swallowed.
php artisan themes:sync || true

echo "[entrypoint] caching configuration"

php artisan config:cache
php artisan view:cache

# No route:cache. routes/web.php and routes/install.php define six closure routes —
# `Route::get('/branding', fn () => redirect()->route('reseller.appearance'))` among
# them — and Laravel cannot serialise a closure. Caching routes would abort the
# deploy. Worth doing properly one day by moving those to controller actions; until
# then this is skipped on purpose rather than forgotten.

echo "[entrypoint] ready"

exec "$@"