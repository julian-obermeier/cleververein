# Bereitstellung auf ALL-INKL

## Paketinhalt

Ein produktives Release enthält `vendor/`, vorkompilierte Dateien in `public/build/` und keine Entwicklungsabhängigkeit von Node.js. Der aktuelle Git-Stand ist ein Entwicklungsstand; das vollständige Releasepaket folgt in Phase 9.

## Vorbereitung

1. PHP 8.4 oder 8.5 im KAS aktivieren.
2. Eine MySQL-/MariaDB-Datenbank und einen eigenen Datenbankbenutzer anlegen.
3. Release-ZIP in ein Verzeichnis außerhalb des öffentlichen Webroots entpacken.
4. Die Domain ausschließlich auf den Unterordner `public/` zeigen lassen.
5. Schreibrechte für `storage/` und `bootstrap/cache/` sicherstellen.
6. Prüfen, dass `.env`, `vendor/`, `storage/` und Quellverzeichnisse nicht direkt per HTTP erreichbar sind.
7. Domain aufrufen und den Installer abschließen.

## Cronjob

Einmal pro Minute:

```bash
php84 /vollstaendiger/pfad/artisan schedule:run >/dev/null 2>&1
```

Der genaue PHP-Binary-Name und Pfad wird im ALL-INKL-KAS angezeigt. Hintergrundjobs werden vom Scheduler zeitlich begrenzt und stapelweise gestartet; es ist kein permanenter Prozess erforderlich.

## Sicherheit nach Installation

- `APP_ENV=production`, `APP_DEBUG=false`
- HTTPS erzwingen
- Dateirechte so eng wie möglich setzen
- regelmäßige Datenbank- und Dateisicherungen außerhalb des Webroots
- Installer-Lock `storage/app/installed` nicht entfernen
- E-Mail-Test und Cronstatus in der späteren Systemadministration prüfen

## Updateprinzip

Bis das Web-Updatesystem in Phase 9 abgeschlossen ist, sind Git-Stände keine Endkunden-Updatepakete. Produktive Datenbanken niemals manuell verändern. Jede Schemaänderung erfolgt über vorwärtskompatible Laravel-Migrationen.
