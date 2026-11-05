FROM php:8.5.11-fpm-bookworm@sha256:9e1dc1e2000d88569f9673eb58b6026fe242067084d9008ceafb516a566574e3

RUN php -r 'exit(version_compare(PHP_VERSION, "8.4.27", ">=") ? 0 : 1);'

WORKDIR /var/www/avatarvault

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl git libonig-dev libpng-dev libxml2-dev mariadb-client unzip zip \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/security.ini /usr/local/etc/php/conf.d/zz-avatarvault-security.ini
COPY docker/php/banner.sh /usr/local/bin/banner.sh
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/banner.sh /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["-F"]
