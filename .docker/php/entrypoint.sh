#!/bin/sh
set -eu

log() {
    level="$1"
    message="$2"
    printf '[%s] %s %s\n' "$(date '+%d-%b-%Y %H:%M:%S')" "$level" "$message"
}

app_version() {
    version_file="${APP_ROOT}/config/app-version.json"

    if [ ! -f "$version_file" ]; then
        return
    fi

    sed -nE 's/^[[:space:]]*"display_version"[[:space:]]*:[[:space:]]*"([^"]+)".*$/\1/p' "$version_file" | sed -n '1p'
}

show_application_information() {
    display_version="$(app_version || true)"

    if [ -f "${APP_ROOT}/.docker/php/banner.sh" ]; then
        /bin/sh "${APP_ROOT}/.docker/php/banner.sh"
    elif [ -f /usr/local/bin/core-panel-banner.sh ]; then
        /bin/sh /usr/local/bin/core-panel-banner.sh
    else
        printf '\nAvatarVault startup\n'
    fi

    printf '\n'
    if [ -n "$display_version" ]; then
        printf '🏷️  Version:   %s\n' "$display_version"
    fi
    printf '👤 User:      %s  PUID:%s\n' "$(id -un)" "$(id -u)"
    printf '👥 Group:     %s  PGID:%s\n' "$(id -gn)" "$(id -g)"
    printf '🐘 PHP:       %s\n' "$(php -v | sed -n '1p')"
    printf '\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n'
}

is_enabled() {
    value="$(printf '%s' "${1:-}" | tr '[:upper:]' '[:lower:]')"

    case "$value" in
        1|true|yes|on)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

has_artisan_command() {
    command="$1"

    if [ ! -f "${APP_ROOT}/artisan" ]; then
        return 1
    fi

    php artisan list --raw 2>/dev/null | grep -Fxq "$command"
}

ensure_public_storage_link() {
    public_storage_path="${APP_ROOT}/public/storage"
    storage_target="${APP_ROOT}/storage/app/public"

    mkdir -p "$storage_target"
    mkdir -p "$(dirname "$public_storage_path")"

    if [ -L "$public_storage_path" ]; then
        if [ "$(readlink "$public_storage_path")" = "$storage_target" ]; then
            log "✅ success " "public/storage symlink is already configured"
            return
        fi

        ln -sfn "$storage_target" "$public_storage_path"
        log "✅ success " "Updated public/storage symlink"
        return
    fi

    if [ -e "$public_storage_path" ]; then
        log "⚠️ WARNING " "Skipping public/storage symlink because it already exists and is not a symlink"
        return
    fi

    ln -s "$storage_target" "$public_storage_path"
    log "✅ success " "Created public/storage symlink"
}

APP_ROOT="${APP_ROOT:-/var/www/html}"
MAX_RETRIES="${MAX_RETRIES:-30}"
SLEEP_SECONDS="${SLEEP_SECONDS:-5}"
command_name="${DOCKER_CMD:-/init}"

command_name="${command_name%% *}"

cd "$APP_ROOT"

show_application_information

if [ -f "${APP_ROOT}/.env" ]; then
    log "📥 info    " ".env detected at ${APP_ROOT}/.env"
elif [ -n "${APP_ENV:-}" ] || [ -n "${APP_KEY:-}" ]; then
    log "📥 info    " ".env not found at ${APP_ROOT}/.env; using injected environment variables"
else
    log "⚠️ WARNING " ".env not found at ${APP_ROOT}/.env"
fi

mkdir -p \
    storage/app \
    storage/app/public \
    storage/framework \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chmod u+rwX,g+rwX \
    storage \
    storage/app \
    storage/app/public \
    storage/framework \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "$(id -u)" -eq 0 ]; then
    runtime_user="${PHP_FPM_CHILD_PROCESS_USER:-www-data}"
    runtime_group="${PHP_FPM_CHILD_PROCESS_GROUP:-www-data}"
fi

if [ "${DB_CONNECTION:-}" = "pgsql" ]; then
    db_host="${DB_HOST:-postgres}"
    db_port="${DB_PORT:-5432}"
    db_user="${DB_USERNAME:-postgres}"
    db_name="${DB_DATABASE:-postgres}"
    attempt=0

    log "🔍 info    " "Waiting for PostgreSQL at ${db_host}:${db_port}/${db_name}"

    export DB_HOST="$db_host"
    export DB_PORT="$db_port"
    export DB_USERNAME="$db_user"
    export DB_DATABASE="$db_name"

    until php -r '
        $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE"));

        try {
            new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);
            exit(0);
        } catch (Throwable $exception) {
            exit(1);
        }
    ' >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge "$MAX_RETRIES" ]; then
            log "🚫 ERROR   " "PostgreSQL not reachable after ${MAX_RETRIES} attempts"
            exit 1
        fi
        log "⏱ WARNING  " "Attempt ${attempt}/${MAX_RETRIES} – waiting ${SLEEP_SECONDS} seconds..."
        sleep "$SLEEP_SECONDS"
    done

    log "✅ success " "PostgreSQL is reachable"
elif [ "${DB_CONNECTION:-}" = "mysql" ]; then
    db_host="${DB_HOST:-mysql}"
    db_port="${DB_PORT:-3306}"
    db_user="${DB_USERNAME:-root}"
    attempt=0

    log "🔍 info    " "Waiting for MySQL at ${db_host}:${db_port}"

    until MYSQL_PWD="${DB_PASSWORD:-}" mysql \
        -h "$db_host" \
        -P "$db_port" \
        -u "$db_user" \
        -e "SELECT 1" >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ "$attempt" -ge "$MAX_RETRIES" ]; then
            log "🚫 ERROR   " "MySQL not reachable after ${MAX_RETRIES} attempts"
            exit 1
        fi
        log "⏱ WARNING  " "Attempt ${attempt}/${MAX_RETRIES} – waiting ${SLEEP_SECONDS} seconds..."
        sleep "$SLEEP_SECONDS"
    done

    log "✅ success " "MySQL is reachable"
else
    log "⚠️ WARNING " "Skipping database wait for DB_CONNECTION=${DB_CONNECTION:-unset}"
fi

ensure_public_storage_link

if is_enabled "${RUN_MIGRATIONS:-false}"; then
    if [ "$command_name" = "/init" ] || [ "$command_name" = "php-fpm" ]; then
        if has_artisan_command 'migrate:recursive'; then
            log "ℹ️ info    " "Running php artisan migrate:recursive --force"
            php artisan migrate:recursive --force
            log "✅ success " "Central recursive migrations completed"
        else
            log "ℹ️ info    " "Running php artisan migrate --force"
            php artisan migrate --force
            log "✅ success " "Central migrations completed"
        fi

        if has_artisan_command 'tenants:migrate'; then
            log "ℹ️ info    " "Running php artisan tenants:migrate --force"
            php artisan tenants:migrate --force
            log "✅ success " "Tenant migrations completed"
        else
            log "ℹ️ info    " "Skipping tenant migrations because the tenancy addon is not installed"
        fi
    else
        log "ℹ️ info    " "Skipping migrations for worker command: ${command_name:-unknown}"
    fi
fi

if [ "$(id -u)" -eq 0 ]; then
    chown -R "${runtime_user}:${runtime_group}" storage bootstrap/cache
    log "✅ success " "Prepared writable runtime directories for ${runtime_user}:${runtime_group}"
fi

log "✅ success " "AvatarVault initialization completed for: ${DOCKER_CMD:-unknown}"
