# Changelog

## 0.6.0 – Finanzjournal & Berichte

- mandantenfähige Finanzkonten für Bank, Kasse, Verrechnung und weitere frei definierbare Konten
- frei definierbare Einnahmen- und Ausgabenkategorien mit Standard-Steuersatz
- unveränderliches Finanzjournal mit fortlaufenden `BU-YYYY-xxxxxx`-Buchungsnummern
- manuelle Einnahmen- und Ausgabenbuchungen inklusive Netto-, Steuer- und Bruttobeträgen
- Korrektur manueller Buchungen über Gegenbuchungen statt Löschung
- automatische Journalbuchung von Rechnungzahlungen
- Dublettenschutz pro Zahlung und Bankumsatz
- Übernahme bereits bestehender Rechnungzahlungen beim Update
- Kontostände inklusive Eröffnungsbestand
- Monats-, Jahres- und Kategorieauswertungen
- filterbarer CSV-Journalexport
- neue Rechte `finance.accounting` und `finance.reports`
- Standardkonten und Basiskategorien werden für bestehende und neue Mandanten idempotent bereitgestellt
- Browser-Installer aktualisiert auf 0.6.0 und Administratorrolle erhält alle vorhandenen Systemrechte
- Featuretests für Routen, Standardstammdaten, Steuerberechnung, Storno, automatische Zahlungsbuchung und Tenant-Isolation

## 0.5.0 – Erweiterte Finanzoperationen

- private Rechnungs-, Mahnungs- und Gutschrift-PDFs
- Empfänger-Snapshot beim Ausstellen einer Rechnung
- Gutschriften mit eigener Jahressequenz und optionalem Vollstorno
- Haushalts-/Familienbeiträge
- Finanzstammdaten mit verschlüsselten Bankdaten
- SEPA-Core-XML als `pain.008.001.08` mit FRST/RCUR-Lebenszyklus
- SEPA-Läufe mit getrenntem Erzeugen, Exportieren und Einreichen
- CSV-Bankimport mit automatischer und manueller Zahlungszuordnung
- Datei- und Umsatz-Dublettenschutz beim Bankimport
- Schutz vor unkontrollierten Überzahlungen
- eigene Rechte für Finanzdokumente und Bankabgleich

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
