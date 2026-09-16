# Changelog

## 0.2.0 – Phase 2 Mitglieder und Organisation (in Umsetzung)

- tenant-sichere Mitgliederverwaltung auf Basis zentraler Personen
- Mitgliedsnummer, Status, Eintritt, Austritt, Kontaktdaten und interne Notizen
- Mitgliedersuche und Filter nach Status sowie Organisationseinheit
- Archivierung und Wiederherstellung von Mitgliedern
- beliebig viele Mitgliedschaften pro Person und Gliederung
- Standardtypen für Dach-, Bundes-, Landes-, Bezirks-, Kreis- und Ortsverbände sowie Vereine und Untergliederungen
- frei definierbare zusätzliche Organisationstypen
- Organisationsverwaltung mit Closure-Table-Hierarchie und sicherem Verschieben von Gliederungen
- Phase-2-Rechte und Administratorrolle
- echte Mitglieder- und Organisationskennzahlen im Dashboard
- Navigation und globale Mitgliedersuche aktiviert
- Featuretests für Mitgliederverwaltung und Organisationshierarchie

## 0.1.0 – Phase 1 Fundament

- Laravel-13-Grundgerüst für PHP 8.4+
- Browserbasierter Installationsassistent mit Installationssperre
- Trennung von Person, Benutzerkonto und Mandant
- Requestgebundener Mandantenkontext mit automatischem Eloquent-Scope
- Frei tiefe Organisationshierarchie per Closure Table
- Rollen, direkte Rechte, Gültigkeitszeiträume und Gliederungsscope
- Unveränderliche Audit-Grundlage und protokollierter Supportkontext
- Responsives, barrierearmes App-Shell- und Dashboard-Design
- Login mit Rate Limiting, Session-Erneuerung und Kontosperre
- Erste Tenant-Isolation- und Installer-Tests
