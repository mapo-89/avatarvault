FROM node:26-bookworm AS node

FROM serversideup/php:8.6-fpm-nginx AS php-extension-base

ARG POSTGRES_CLIENT_MAJOR=18

USER root
WORKDIR /var/www/html

RUN php -r 'exit(version_compare(PHP_VERSION, "8.5.12", ">=") ? 0 : 1);'

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc https://www.postgresql.org/media/keys/ACCC4CF8.asc \
    && . /etc/os-release \
    && printf '%s\n' \
        'Types: deb' \
        'URIs: https://apt.postgresql.org/pub/repos/apt' \
        "Suites: ${VERSION_CODENAME}-pgdg" \
        "Architectures: $(dpkg --print-architecture)" \
        'Components: main' \
        'Signed-By: /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc' \
        > /etc/apt/sources.list.d/pgdg.sources \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        postgresql-client-${POSTGRES_CLIENT_MAJOR} \
        sqlite3 \
    && install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
    && rm -rf /var/lib/apt/lists/*

FROM php-extension-base AS php-build-base

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && rm -rf /var/lib/apt/lists/*

FROM php-build-base AS composer-base

COPY composer.json composer.lock ./

FROM composer-base AS vendor-prod

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts

FROM composer-base AS vendor-dev

RUN composer install \
    --no-interaction \
    --no-progress \
    --no-scripts

FROM php-build-base AS frontend-build

COPY package.json package-lock.json ./

RUN npm ci

COPY . .
COPY --from=vendor-prod /var/www/html/vendor /var/www/html/vendor
COPY .env.example .env

RUN mkdir -p storage/framework/views bootstrap/cache \
    && export APP_ENV=production APP_KEY=base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE= \
    && php artisan package:discover --ansi \
    && npm run build

FROM php-extension-base AS app-runtime-base

COPY . /var/www/html
COPY --from=php-build-base /usr/bin/composer /usr/bin/composer
COPY .docker/php/entrypoint.sh /etc/entrypoint.d/60-core-panel.sh
COPY .docker/php/banner.sh /usr/local/bin/core-panel-banner.sh
COPY .docker/nginx/default.conf /etc/nginx/site-opts.d/http.conf.template
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/zzz-core-panel.ini
COPY .docker/php/security.ini /usr/local/etc/php/conf.d/zzz-avatarvault-security.ini
COPY .docker/php/opcache.ini /usr/local/etc/php/conf.d/zzz-core-panel-opcache.ini

RUN mkdir -p \
        /var/www/html/storage/app/public \
        /var/www/html/storage/framework/cache \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/.docker/cache/views \
        /var/www/html/bootstrap/cache \
    && ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage \
    && chmod 755 /etc/entrypoint.d/60-core-panel.sh /usr/local/bin/core-panel-banner.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/.docker/cache /var/www/html/bootstrap/cache \
    && chmod -R u+rwX,g+rwX,o+rX /var/www/html/storage /var/www/html/.docker/cache /var/www/html/bootstrap/cache

ENV NGINX_WEBROOT=/var/www/html/public \
    HEALTHCHECK_PATH=/up \
    NGINX_CLIENT_MAX_BODY_SIZE=256M \
    PHP_MAX_EXECUTION_TIME=120 \
    PHP_MEMORY_LIMIT=512M \
    PHP_OPCACHE_ENABLE=1 \
    PHP_POST_MAX_SIZE=256M \
    PHP_UPLOAD_MAX_FILE_SIZE=256M

FROM app-runtime-base AS app-dev

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN docker-php-serversideup-set-id www-data "${USER_ID}:${GROUP_ID}"

COPY --from=vendor-dev /var/www/html/vendor /var/www/html/vendor
COPY --from=frontend-build /var/www/html/public/build /var/www/html/public/build

RUN export APP_ENV=production APP_KEY=base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE= \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data \
        /var/www/html/vendor \
        /var/www/html/public/build \
        /var/www/html/storage \
        /var/www/html/.docker/cache \
        /var/www/html/bootstrap/cache

USER www-data

EXPOSE 8080

FROM app-runtime-base AS app-prod

COPY --from=vendor-prod /var/www/html/vendor /var/www/html/vendor
COPY --from=frontend-build /var/www/html/public/build /var/www/html/public/build

RUN export APP_ENV=production APP_KEY=base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE= \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data \
        /var/www/html/vendor \
        /var/www/html/public/build \
        /var/www/html/storage \
        /var/www/html/.docker/cache \
        /var/www/html/bootstrap/cache

USER www-data

EXPOSE 8080
