# cleververein

cleververein wird als mandantenfähiges Betriebssystem für Vereine und mehrstufige Verbände entwickelt. Der aktuelle Stand ist **Phase 1 / Fundament (0.1.0)**. Er ist bewusst kein als vollständig ausgegebenes Scheinprodukt: Fachmodule werden erst aktiviert, wenn Backend, Autorisierung und Tests vorhanden sind.

## Aktueller Funktionsumfang

- Laravel 13.17, PHP 8.4+, MySQL/MariaDB, Blade und Tailwind CSS 4
- browserbasierter Installer ohne vorausgesetzte SSH-Nutzung beim Endkunden
- Benutzeranmeldung, Brute-Force-Schutz, Sitzungswechsel und Kontosperre
- zentrale Personen und getrennte Benutzerkonten
- SaaS-Mandanten mit aktiver Tenant-Auflösung
- beliebig tiefe Organisationsbäume über Closure Table
- Rollen, Einzelrechte, Gliederungsscope, Untergliederungen und Zeiträume
- Audit-Log-Grundlage und sichtbarer Supportmodus
- responsives Dashboard und zugängliches Designsystem

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

## Qualität

```bash
composer test
./vendor/bin/pint --test
npm run build
```

Die Architekturentscheidungen stehen in [docs/architecture/ARCHITECTURE.md](docs/architecture/ARCHITECTURE.md), das Datenmodell in [docs/architecture/DATA-MODEL.md](docs/architecture/DATA-MODEL.md) und der aktuelle Phasenstatus in [docs/PHASES.md](docs/PHASES.md).
