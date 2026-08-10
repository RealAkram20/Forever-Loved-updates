# Forever Loved — production image.
#
# Three stages so the runtime carries neither Node nor Composer: assets are built
# once, dependencies resolved once, and only their output is copied forward.
#
# nginx and PHP-FPM share the container under supervisor. Two processes in one
# container is unfashionable, but a PHP-FPM pool is useless without a web server in
# front of it, and splitting them buys nothing here — they scale together and die
# together. supervisor also runs the scheduler, which on shared hosting was a cron
# line; see docker/supervisord.conf for why that matters more than it looks.

# ─── Stage 1: front-end assets ────────────────────────────────────────────────
FROM node:22-alpine AS assets
WORKDIR /app

# package files alone first, so a change to application code does not invalidate
# the dependency layer and force a full npm install on every deploy.
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund

# No postcss.config or tailwind.config: this is Tailwind 4 through
# @tailwindcss/vite, which is configured from resources/css/app.css instead. A COPY
# whose wildcard matches nothing fails the build, so they are not listed "just in
# case" — if either file is ever added, add it here too.
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# ─── Stage 2: PHP dependencies ────────────────────────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app

COPY . .

# --no-scripts: package:discover runs artisan, which wants the extensions the
# runtime stage installs and this one does not have. Laravel regenerates the
# manifest on first boot anyway.
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist

# ─── Stage 3: runtime ─────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS app

# gd and dompdf render the PDF reports; zip is used by openspout and by the
# archive handling; gmp makes web-push's signing tolerable rather than glacial.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        gmp \
        opcache \
    && apk add --no-cache nginx supervisor

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-forever.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# storage/ is mounted over by a persistent volume at runtime — these exist so a
# first boot against an empty volume still has somewhere to write.
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/app/public \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -rf /var/www/html/node_modules

# 8080, not 80: nothing here runs as root, and a privileged port would require it.
EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]