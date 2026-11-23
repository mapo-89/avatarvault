FROM php:8.6.0-fpm-bookworm@sha256:a836c00ddc6d873275d40437eb74732bde56781ead316b9eff42fa46dc6d93db

RUN php -r 'exit(version_compare(PHP_VERSION, "8.5.12", ">=") ? 0 : 1);'

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
