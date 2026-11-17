<p align="center">
  <img src="public/images/brand/readme-logo.svg" alt="AvatarVault — self-hosted avatar service" width="480">
</p>

# AvatarVault

[![CI](https://github.com/mapo-89/avatarvault/actions/workflows/ci.yml/badge.svg)](https://github.com/mapo-89/avatarvault/actions/workflows/ci.yml)
[![Latest Release](https://img.shields.io/github/v/release/mapo-89/avatarvault)](https://github.com/mapo-89/avatarvault/releases)
[![PHP](https://img.shields.io/badge/PHP-8.5%2B-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20.svg?logo=laravel&logoColor=white)](https://laravel.com/)
[![CorePanel](https://img.shields.io/badge/CorePanel-1.7.1-174a3f.svg)](https://github.com/mapo-89/core-panel-monorepo)
[![Avatar Manager](https://img.shields.io/badge/Avatar%20Manager-2.0-174a3f.svg)](https://github.com/mapo-89/laravel-avatar-manager)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE.md)
[![GitHub issues](https://img.shields.io/github/issues/mapo-89/avatarvault.svg)](https://github.com/mapo-89/avatarvault/issues)
[![GitHub stars](https://img.shields.io/github/stars/mapo-89/avatarvault.svg?style=flat)](https://github.com/mapo-89/avatarvault/stargazers)
[![Last commit](https://img.shields.io/github/last-commit/mapo-89/avatarvault.svg)](https://github.com/mapo-89/avatarvault/commits/main)

<a href="https://buymeacoffee.com/mapo"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me a Coffee" height="60"></a>

Self-hosted avatars with a public email-hash URL. Upload once, embed anywhere,
and keep your profile pictures on your own infrastructure.

[Deutsche Dokumentation](README.de.md)

## Features

- Upload and manage profile pictures with public MD5/SHA-256 avatar URLs.
- Verified email aliases sharing the same profile picture.
- OIDC/Authentik login, two-factor security and administration through CorePanel.
- Protected API uploads, German and English UI, and dark mode.

## Requirements

PHP 8.5+, Composer, Node.js 24+ with npm, and MySQL or PostgreSQL.
AvatarVault uses Laravel 13, CorePanel 1.7.1 and Avatar Manager 2.x.
Redis is only required when running Horizon.

## Installation

```bash
git clone https://github.com/mapo-89/avatarvault.git
cd avatarvault
cp .env.example .env
composer install
php artisan key:generate
```

Configure `APP_URL`, database access and mail delivery in `.env`, then run:

```bash
php artisan migrate
php artisan core-panel:sync-access
php artisan passport:keys
php artisan storage:link
npm ci
npm run build
```

The public document root must point at `public/`. Store the application key,
database and `storage/` persistently. Set `APP_ENV=production` and
`APP_DEBUG=false` for production.

### First administrator

Register a normal account, then grant administration explicitly:

```bash
php artisan core-panel:user:assign-super-admin admin@example.com
```

Replace `admin@example.com` with the existing administrator account.
Manage avatars at `/dashboard` and administer the application at `/admin`.

### Local development

```bash
php artisan serve
npm run dev
```

Run Horizon separately with `php artisan horizon` when Redis-backed queues are
configured. `php artisan schedule:work` runs scheduled CorePanel maintenance.

## Embed avatars

Trim and lowercase the email address, then hash it with MD5 or SHA-256:

```html
<img src="https://avatars.example.com/avatar/EMAIL_HASH" alt="Avatar" width="80" height="80">
```

Primary addresses and verified aliases resolve to the same image. Hash URLs are
public and provide no access control. Unknown hashes return a default image.

Add further addresses under **Dashboard → Email aliases**. Working mail delivery
is required to confirm them.

See the [operations guide](docs/operations.md) for configuration, OIDC/Authentik,
API uploads and migration of existing installations.

## Development

```bash
composer qa
npm run qa
```

PHP tests use the isolated `.env.testing` configuration. CI also checks PostgreSQL
migrations, the frontend build and dependencies.
See the [release guide](.release/README.md) for release and dependency policies.

## Security, credits and license

Report security issues privately to [info@postler.de](mailto:info@postler.de).
Maintained by [Manuel Postler](https://github.com/mapo-89).
Licensed under the [MIT License](LICENSE.md).
