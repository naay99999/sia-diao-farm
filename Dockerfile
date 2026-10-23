# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1.13-php8.5-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends sqlite3 unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-farm.ini

WORKDIR /app

# PHP dependencies, cached separately from the source.
FROM base AS vendor

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Frontend assets. The Wayfinder Vite plugin runs `php artisan`, so this stage needs PHP and vendor too.
FROM base AS assets

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN composer dump-autoload --no-dev --no-scripts --no-interaction \
    && npm run build

FROM base AS app

ENV SERVER_NAME=:80

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --no-scripts --optimize --no-interaction

COPY docker/entrypoint.sh /usr/local/bin/farm-entrypoint
RUN chmod +x /usr/local/bin/farm-entrypoint

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/up") === false ? 1 : 0);'

ENTRYPOINT ["farm-entrypoint"]
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
