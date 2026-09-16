# cleververein

cleververein wird als mandantenfähiges Betriebssystem für Vereine und mehrstufige Verbände entwickelt. Der aktuelle Stand ist **Phase 2 / Mitglieder und Organisation (0.2.0, in Umsetzung)**. Das Fundament aus Phase 1 ist produktiv lauffähig; Fachmodule werden nur aktiviert, wenn Backend, Autorisierung und Tests vorhanden sind.

## Aktueller Funktionsumfang

- Laravel 13.17, PHP 8.4+, MySQL/MariaDB, Blade und Tailwind CSS 4
- browserbasierter Installer ohne vorausgesetzte SSH-Nutzung beim Endkunden
- Benutzeranmeldung, Brute-Force-Schutz, Sitzungswechsel und Kontosperre
- zentrale Personen und getrennte Benutzerkonten
- SaaS-Mandanten mit aktiver Tenant-Auflösung
- beliebig tiefe Organisationsbäume über Closure Table
- frei definierbare Organisationstypen für Verein und mehrstufige Verbände
- sichere Anlage, Bearbeitung und Verschiebung von Organisationseinheiten
- Rollen, Einzelrechte, Gliederungsscope, Untergliederungen und Zeiträume
- Mitgliederstammdaten mit Mitgliedsnummer, Status, Eintritt/Austritt, Kontaktdaten und Notizen
- Suche und Filter für Mitglieder
- Archivierung und Wiederherstellung von Mitgliedern
- mehrere parallele Mitgliedschaften einer Person in verschiedenen Gliederungen
- Audit-Log-Grundlage und sichtbarer Supportmodus
- Dashboard mit echten Mitglieder- und Organisationskennzahlen
- responsives, zugängliches Designsystem

## Entwicklungsinstallation

Voraussetzungen: PHP 8.4+, Composer 2, Node.js 22+, MySQL 8 oder MariaDB 10.6+.

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci
npm run build
php artisan serve
```

Rufen Sie anschließend `/install` auf. Für Produktion gelten die zusätzlichen Schritte in [docs/deployment/ALL-INKL.md](docs/deployment/ALL-INKL.md).

## Update einer bestehenden Installation

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
php artisan optimize
```

Vor produktiven Updates wird ein Datenbank- und Dateibackup empfohlen.

## Qualität

```bash
composer test
./vendor/bin/pint --test
npm run build
```

Die GitHub-Quality-Pipeline führt Composer-Installation, Vite-Build, Pint und PHPUnit bei jedem Push aus.

Die Architekturentscheidungen stehen in [docs/architecture/ARCHITECTURE.md](docs/architecture/ARCHITECTURE.md), das Datenmodell in [docs/architecture/DATA-MODEL.md](docs/architecture/DATA-MODEL.md) und der aktuelle Phasenstatus in [docs/PHASES.md](docs/PHASES.md).
