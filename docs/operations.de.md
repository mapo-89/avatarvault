# Betrieb und Migration

[Zur Übersicht](../README.de.md)

## Konfiguration

| Variable | Standard | Zweck |
| --- | --- | --- |
| `APP_NAME` | `AvatarVault` | Name der Anwendung. |
| `APP_URL` | `http://localhost` | Basis für Avatar- und Medien-URLs. |
| `APP_LOCALE` | `de` im Beispiel | Anfängliche Oberflächensprache. |
| `AVATAR_DISK` | `public` | Dateisystem für Profilbilder und API-Uploads. |
| `AVATAR_API_KEY` | leer | Schlüssel für autorisierte Backend-Uploads. |
| `REGISTRATION_ENABLED` | `true` | Selbstregistrierung; auch in CorePanel einstellbar. |
| `EMAIL_VERIFICATION_ENABLED` | `false` | Optionale E-Mail-Verifikation; erst bei funktionierendem Mailversand aktivieren. |

CorePanel übernimmt Laufzeiteinstellungen und Rollen. Paketoberflächen bleiben
in `vendor`; Einstiegspunkte und AvatarVault-Seiten gehören der Anwendung.
Vor späteren CorePanel-Updater-Läufen eigene Anpassungen prüfen. Den
Neuinstallationsgenerator nicht über eine bestehende AvatarVault-Datenbank laufen lassen.

### Vertrauenswürdige Proxies

`bootstrap/trust_proxies.php` bleibt eingebunden. Nur tatsächlich vertrauenswürdige
Proxy-Adressen eintragen, beispielsweise:

```php
<?php
$middleware->trustProxies(['172.18.0.0/16']);
```

## Authentik / OIDC

Den exakten Issuer der Authentik-Anwendung aus deren Discovery-Dokument verwenden:

```dotenv
SOCIAL_OIDC_ENABLED=true
OIDC_ISSUER="https://auth.example.com/application/o/avatarvault/"
OIDC_CLIENT_ID="your-client-id"
OIDC_CLIENT_SECRET="your-client-secret"
OIDC_REDIRECT_URI="https://avatars.example.com/auth/oidc/callback"
```

Genau diese Redirect-URI in Authentik hinterlegen. Der Issuer enthält den
Anwendungs-Slug und ist nicht bloß die Server-Basisadresse. Zugangsdaten in `.env`
oder unter **Einstellungen → Authentifizierung** in CorePanel pflegen.
Nach Änderungen den Konfigurationscache leeren.

`/auth/authentik` leitet auf CorePanels OIDC-Anmeldung um. Bestehende Authentik-Subjects
werden mit unverändertem Subject und Benutzer-ID als Provider `oidc` nach
`social_accounts` übernommen. Dafür dieselbe Authentik-Anwendung und denselben
Issuer weiterverwenden. Alte `oidc_*`-Felder und Gruppen bleiben erhalten;
Gruppen-Claims vergeben keine automatischen Administratorrollen in CorePanel.
Die bisherige `AUTHENTIK_*`-Konfiguration durch `OIDC_*` ersetzen.

## Avatare einbinden und hochladen

```php
use Mapo89\LaravelAvatarManager\Facades\AvatarManager;

$url = AvatarManager::url('user@example.com');
```

```html
<img src="https://avatars.example.com/avatar/EMAIL_MD5_HASH" alt="Avatar" width="80" height="80">
```

Der Endpunkt akzeptiert MD5 (32 Hexadezimalzeichen) und SHA-256 (64
Hexadezimalzeichen), auch in Großbuchstaben. Vor dem Hashen Leerzeichen am
Anfang und Ende entfernen und die E-Mail-Adresse kleinschreiben:

```php
$hash = hash('sha256', strtolower(trim('user@example.com')));
$url = url('/avatar/'.$hash);
```

Beide Formate liefern für Hauptadressen und bestätigte Aliasse dasselbe Bild.
Neue API-Uploads unterstützen beide Formate auch ohne Konto. Ältere reine
API-Bilder ohne zugehöriges Konto müssen erneut hochgeladen werden, um SHA-256
zu aktivieren; aus einem alten MD5-Dateinamen lässt sich die Adresse nicht
wiederherstellen.

Hash-URLs sind öffentlich. Hashing anonymisiert keine E-Mail-Adressen und ist kein
Zugriffsschutz. Bei einer neuen E-Mail-Adresse ändert sich die URL. Verwaltete
Profilbilder haben Vorrang vor alten API-Uploads. Unbekannte Hashes liefern das
mitgelieferte Standardbild; ungültige Hash-Formate liefern `404`.

Backend-Upload ohne Benutzerregistrierung:

```bash
curl --request POST 'https://avatars.example.com/api/avatars/upload' \
  --header "X-API-KEY: $AVATAR_API_KEY" \
  --form 'email=user@example.com' \
  --form 'avatar=@profile.png'
```

Erlaubt sind JPEG, PNG, GIF und WebP bis 2 MiB. Antworten: `200` gespeichert,
`401` ungültiger Schlüssel, `409` verwaltetes Profilbild vorhanden,
`422` ungültige Eingabe. Schlüssel gehören nicht in Browser-Code.
Die Konto-API verwendet jetzt Passport; der separate Avatar-Upload per
`X-API-KEY` bleibt erhalten.

### Zusätzliche E-Mail-Adressen

Unter **Dashboard → E-Mail-Aliasse** lassen sich bis zu 20 zusätzliche Adressen
hinterlegen. Jede erhält einen Bestätigungslink, der 60 Minuten gültig ist. Öffne
ihn angemeldet mit dem Konto, das den Alias angelegt hat. Erst nach der
Bestätigung liefert die Adresse dasselbe Profilbild; ihre eigene Hash-URL lässt
sich dann im Dashboard kopieren.

Bildänderungen gelten für alle bestätigten Adressen. API-Uploads an einen
bestätigten Alias aktualisieren ebenfalls das Bild der Hauptadresse; der Schutz
verwalteter Profilbilder bleibt bestehen. Beim Entfernen eines Alias bleibt das
Profilbild erhalten. Aliasse dienen nicht als zusätzliche Anmeldenamen.
Bestehende Kontoadressen und bereits anderweitig bestätigte Aliasse sind gesperrt.
Der Mailversand muss auch bei deaktivierter Kontoverifizierung funktionieren.
Grenzen und Linklaufzeit stehen in `config/avatarvault.php`.

## Bestehende Installation umstellen

Zuerst Datenbank, `.env`, App-Schlüssel, Uploads und bisherigen Anwendungsstand
sichern. Datenbank, `APP_KEY` und Bild-Dateisystem beibehalten. Webzugriffe und
Queue-Worker während der Umstellung anhalten. Im aktualisierten Checkout:

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

Importergebnis vor Wiederaufnahme des Betriebs prüfen. Fehlende Dateien oder
Speicherfehler beheben und den Import wiederholen. Er ist idempotent und meldet
ungelöste Bilder mit einem Fehlerstatus. Kein `migrate:fresh` verwenden.

- Numerische IDs, Passwort-Hashes, E-Mails und Migrationshistorie bleiben erhalten.
- Vor- und Nachname werden für CorePanel ergänzt; die bisherige `name`-Spalte bleibt.
- Gezielte Host-Migrationen erhalten numerische Fremdschlüssel in CorePanel/Passport.
- Der Bildimport kopiert Jetstream-Bilder in die Sammlung `avatars`, behält Originaldateien
  und entfernt alte Verweise erst nach erfolgreicher Übernahme.
- Authentik-Verknüpfungen werden wie oben beschrieben übernommen. Callback und
  Umgebungsvariablen vor Aktivierung der OIDC-Anmeldung aktualisieren.
- Alte Sanctum-Token bleiben gespeichert, authentifizieren aber nicht mehr.
  API-Clients benötigen neue Passport-Token; der Avatar-Upload-Schlüssel bleibt gültig.
- Zwei-Faktor-Secrets und Wiederherstellungscodes bleiben mit demselben App-Schlüssel nutzbar.

Die Installation der Abhängigkeiten migriert keine Produktionsdaten. Nach
Schemaänderungen benötigt ein Rollback die passende Datenbank-/Dateisicherung
und den vorherigen Checkout; erhaltene Altdateien ersetzen kein Backup.
