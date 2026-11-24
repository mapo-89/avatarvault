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

Selbst gehostete Avatare mit öffentlicher E-Mail-Hash-URL. Einmal hochladen,
überall einbinden und Profilbilder auf der eigenen Infrastruktur behalten.

[English documentation](README.md)

## Funktionen

- Profilbilder hochladen, verwalten und über öffentliche MD5-/SHA-256-URLs einbinden.
- Bestätigte E-Mail-Aliasse für dasselbe Profilbild.
- Anmeldung mit OIDC/Authentik, Zwei-Faktor-Sicherheit und Administration über CorePanel.
- Geschützter API-Upload sowie deutsche und englische Oberfläche mit Darkmode.

## Voraussetzungen

PHP 8.5+, Composer, Node.js 24+ mit npm und MySQL oder PostgreSQL.
AvatarVault basiert auf Laravel 13, CorePanel 1.7.1 und Avatar Manager 2.x.
Redis wird nur beim Betrieb von Horizon benötigt.

## Installation

```bash
git clone https://github.com/mapo-89/avatarvault.git
cd avatarvault
cp .env.example .env
composer install
php artisan key:generate
```

`APP_URL`, Datenbankzugang und Mailversand in `.env` konfigurieren. Danach:

```bash
php artisan migrate
php artisan core-panel:sync-access
php artisan passport:keys
php artisan storage:link
npm ci
npm run build
```

Der Webserver muss `public/` als Document Root verwenden. App-Schlüssel,
Datenbank und `storage/` dauerhaft speichern. In Produktion `APP_ENV=production`
und `APP_DEBUG=false` setzen.

Das Image mit Nginx und PHP ist im [Docker-Betrieb](docs/operations.de.md#docker-betrieb) beschrieben.

### Erstes Administratorkonto

Ein normales Konto registrieren und Administratorrechte ausdrücklich vergeben:

```bash
php artisan core-panel:user:assign-super-admin admin@example.com
```

`admin@example.com` durch das bestehende Administratorkonto ersetzen.
Avatarverwaltung: `/dashboard`; Administration: `/admin`.

### Lokale Entwicklung

```bash
php artisan serve
npm run dev
```

Bei konfigurierten Redis-Queues Horizon mit `php artisan horizon` starten.
`php artisan schedule:work` führt geplante CorePanel-Wartung aus.

## Avatare einbinden

Die E-Mail-Adresse trimmen, kleinschreiben und mit MD5 oder SHA-256 hashen:

```html
<img src="https://avatars.example.com/avatar/EMAIL_HASH" alt="Avatar" width="80" height="80">
```

Hauptadresse und bestätigte Aliasse liefern dasselbe Bild. Hash-URLs sind öffentlich
und bieten keinen Zugriffsschutz. Unbekannte Hashes liefern ein Standardbild.

Unter **Dashboard → E-Mail-Aliasse** lassen sich weitere Adressen hinzufügen.
Für die Bestätigung muss der Mailversand funktionieren.

Konfiguration, OIDC/Authentik, API-Uploads und die Umstellung bestehender
Installationen stehen im [Betriebsleitfaden](docs/operations.de.md).

## Entwicklung

```bash
composer qa
npm run qa
```

PHP-Tests verwenden die isolierte `.env.testing`. Die CI prüft zusätzlich
PostgreSQL-Migrationen, Frontend-Build und Abhängigkeiten.
Release- und Dependency-Regeln stehen im [Release-Leitfaden](.release/README.md).

## Sicherheit, Mitwirkende und Lizenz

Sicherheitsprobleme vertraulich an [info@postler.de](mailto:info@postler.de) melden.
Betreuung durch [Manuel Postler](https://github.com/mapo-89).
Veröffentlicht unter der [MIT-Lizenz](LICENSE.md).
