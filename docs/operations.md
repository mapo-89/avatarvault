# Operations and migration

[Back to overview](../README.md)

## Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_NAME` | `AvatarVault` | Application identity. |
| `APP_URL` | `http://localhost` | Base URL for generated avatar and media links. |
| `APP_LOCALE` | `de` in the example | Initial interface language. |
| `AVATAR_DISK` | `public` | Storage for managed profile images and API uploads. |
| `AVATAR_API_KEY` | empty | Enables authorized backend uploads when set. |
| `REGISTRATION_ENABLED` | `true` | Allows self-registration; also configurable in CorePanel. |
| `EMAIL_VERIFICATION_ENABLED` | `false` | Optional email verification; configure working mail before enabling. |

CorePanel provides runtime settings and role management. Keep package UI assets
in `vendor`; the application owns its entrypoints and AvatarVault pages. For
future package updates, review host changes before running the CorePanel updater.
Do not run the fresh-install generator over an existing AvatarVault database.

### Trusted proxies

The existing `bootstrap/trust_proxies.php` hook remains active. Add only your
trusted proxy addresses there, for example:

```php
<?php
$middleware->trustProxies(['172.18.0.0/16']);
```

## Authentik / OIDC

Use the application's exact issuer from Authentik's discovery document:

```dotenv
SOCIAL_OIDC_ENABLED=true
OIDC_ISSUER="https://auth.example.com/application/o/avatarvault/"
OIDC_CLIENT_ID="your-client-id"
OIDC_CLIENT_SECRET="your-client-secret"
OIDC_REDIRECT_URI="https://avatars.example.com/auth/oidc/callback"
```

Register that exact redirect URI in Authentik. The issuer includes the application
slug and differs from the Authentik server's base URL. Enter credentials in `.env`
or CorePanel's **Settings → Authentication**. Clear cached configuration after changes.

The old `/auth/authentik` entrypoint redirects to CorePanel's OIDC login. Existing
Authentik subjects are copied into `social_accounts` as provider `oidc`, retaining
the original subject and user ID. Configure the same Authentik application/issuer
to retain that identity. Legacy `oidc_*` fields and group claims remain in the
database; group claims do not automatically grant CorePanel administration roles.
Replace the previous `AUTHENTIK_*` environment configuration with `OIDC_*`.

## Embed and upload avatars

```php
use Mapo89\LaravelAvatarManager\Facades\AvatarManager;

$url = AvatarManager::url('user@example.com');
```

```html
<img src="https://avatars.example.com/avatar/EMAIL_MD5_HASH" alt="Avatar" width="80" height="80">
```

The endpoint accepts MD5 (32 hexadecimal characters) and SHA-256 (64 hexadecimal
characters), including uppercase hashes. Hash the trimmed, lowercase email:

```php
$hash = hash('sha256', strtolower(trim('user@example.com')));
$url = url('/avatar/'.$hash);
```

Both formats resolve the same profile picture for primary addresses and verified
aliases. New API uploads also support both formats without an account. Re-upload
older API-only images to enable SHA-256 when their email has no account; the
original email cannot be recovered from an old MD5 filename.

Hash URLs are public identifiers. Hashing does not anonymize email addresses and
provides no access control. Changing the account email changes the avatar URL.
Managed profile images take precedence over earlier API uploads; unknown hashes
return the bundled default image. Invalid hash formats return `404`.

Backend API upload, without creating a user:

```bash
curl --request POST 'https://avatars.example.com/api/avatars/upload' \
  --header "X-API-KEY: $AVATAR_API_KEY" \
  --form 'email=user@example.com' \
  --form 'avatar=@profile.png'
```

JPEG, PNG, GIF and WebP up to 2 MiB are accepted. Responses: `200` stored, `401`
invalid key, `409` existing managed image, `422` invalid input. Keep API keys out
of browser code. Account API authentication now uses Passport; the separate
`X-API-KEY` avatar endpoint is unchanged.

### Additional email addresses

Add up to 20 email aliases under **Dashboard → Email aliases**. Each address
receives a confirmation link valid for 60 minutes. Open it while signed into the
account that added the alias. Only confirmed aliases serve the account's avatar;
their individual hash URLs are available to copy in the dashboard.

All confirmed addresses follow subsequent picture changes. API uploads to a
confirmed alias also update the primary address's avatar, subject to the same
managed-image protection. Removing an alias removes that association without
deleting the profile picture. Aliases do not provide additional sign-in names.
An existing account address or another account's confirmed alias cannot be used.
Working mail delivery is required even when account email verification is disabled.
Limits and link lifetime are configured in `config/avatarvault.php`.

## Migrate an existing installation

Back up the database, `.env`, application key, uploaded files and the previous
application checkout first. Keep the same database, `APP_KEY` and storage disk.
Stop web traffic and queue workers during the migration. In the updated checkout:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan avatarvault:import-legacy-avatars
php artisan core-panel:sync-access
php artisan passport:keys
php artisan storage:link
php artisan optimize:clear
```

Review the import result before resuming traffic and workers. Run the import
again after resolving missing files or storage errors; it is idempotent and
returns a nonzero status for unresolved images. Do not use `migrate:fresh`.

- Numeric user IDs, password hashes, emails and existing migration history remain.
- Name fields are backfilled for CorePanel; the original `name` column stays.
- Host migration overrides retain numeric foreign keys in CorePanel/Passport tables.
- The image importer copies existing Jetstream pictures into `avatars`, retains
  the original files, and clears legacy references only after successful import.
- Existing Authentik identities are migrated as described above. Update the
  provider callback and environment settings before enabling OIDC.
- Existing Sanctum token records are retained but no longer authenticate. Issue
  replacement Passport tokens to API clients. The avatar upload key remains valid.
- Existing two-factor secrets and recovery codes remain usable with the same app key.

Production data is not migrated by dependency installation alone. Rollback after
schema changes requires the matching database/file backup and previous checkout;
retained legacy files are not a substitute for that backup.

## Docker deployment

The CorePanel-based production image bundles PHP 8.5, Nginx, Composer
dependencies and compiled frontend assets. It listens on port 8080 and serves
the Laravel health endpoint at `/up`. The image runs as `www-data`.

Set `APP_KEY`, `APP_URL`, the database connection and mail settings in `.env`.
Use a database hostname reachable from the container. When migrating an existing
installation, keep its application key, database and entire storage directory.
Restore storage into the Compose volume, or replace the volume mount with your
existing storage directory. The storage mount must be writable by `www-data`.
Point the reverse proxy to the published HTTP port.

```bash
docker compose build app
docker compose run --rm --no-deps app php artisan migrate --force
docker compose run --rm --no-deps app php artisan core-panel:sync-access
docker compose run --rm --no-deps app php artisan passport:keys
docker compose up -d app
```

For a fresh installation, generate a key with
`docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show`
and put it in `.env` before running the application. Set `APP_PORT` to change
the published port. Enable the `scheduler` Compose profile for scheduled tasks
and the `horizon` profile when Redis queues and Horizon are configured.
Use the [migration instructions](#migrate-an-existing-installation) when importing
legacy avatars. For reverse proxies, configure trusted addresses in
`bootstrap/trust_proxies.php` before building the image.
