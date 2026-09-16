# Changelog

## 0.8.0 – Spenden, Rücklastschriften & Erstattungen

- eigener Bereich „Spenden & Erstattungen“ mit separaten Rechten
- Rücklastschriften und Erstattungen als unveränderliche Zahlungskorrekturen statt Änderung des ursprünglichen Zahlungseingangs
- Rücklastschrift öffnet den wirksamen Rechnungssaldo wieder und kann Bankgebühren getrennt als Ausgabe buchen
- Erstattungen sind auf tatsächlich vorhandenes Rechnungsguthaben begrenzt
- Rechnungsstatus und `paid_amount` berücksichtigen Zahlungskorrekturen vollständig
- eigenständige Zuwendungsverwaltung mit `SP-YYYY-xxxxxx`-Nummern
- Geldzuwendungen werden automatisch in die Journal-Kategorie „Spenden“ gebucht
- Aufwandsverzicht wird ohne künstlichen Geldfluss dokumentiert
- steuerliche Stammdaten für Freistellungsbescheid, Körperschaftsteuerbescheid oder § 60a AO
- Ausstellung von Zuwendungsbestätigungen nur nach ausdrücklicher Freischaltung und Vollständigkeitsprüfung
- Altersprüfung der steuerlichen Bescheide vor Ausstellung
- vollständiger Spender-, Empfänger- und Steuer-Snapshot je ausgestellter Bestätigung
- private Zuwendungsbestätigungs-PDFs mit `ZB-YYYY-xxxxxx`-Nummern
- nachvollziehbares Storno statt Löschen; neue Bestätigung erhält eine neue Nummer
- Tenant-Scope auch bei optionalen Bankumsatzverknüpfungen
- neue Rechte `finance.adjustments`, `finance.donations` und `finance.donation_certificates`
- Browser-Installer auf 0.8.0 aktualisiert
- Featuretests für Rücklastschrift, Erstattung, Spendenjournal, Bescheidgültigkeit, private PDFs, Snapshots, Storno und Tenant-Isolation

## 0.7.0 – Kasse, Belege & Prüfungen

- private Belegablage direkt an Finanzbuchungen
- Belegdownload nur nach Berechtigungsprüfung
- Belege werden bei Korrekturen als ungültig markiert statt gelöscht
- eigener Bereich „Kasse & Prüfung“
- Kassenbuchansicht für echte Kassenkonten
- CSV-Kassenbuchexport nach Zeitraum
- Kassenabschlüsse mit Systembestand, gezähltem Bestand und Differenz
- abgeschlossene Kassenzeiträume sperren rückwirkende Buchungen serverseitig
- globale Periodensperren für abgeschlossene Buchungszeiträume
- kontrollierte Wiederöffnung mit Audit-Protokoll
- dokumentierte Kassenprüfungen mit Soll-/Istvergleich und Feststellungen
- neue Rechte `finance.receipts`, `finance.cash`, `finance.periods` und `finance.audit`
- Browser-Installer auf 0.7.0 aktualisiert
- Featuretests für Belegstorage, Kassenabschluss, Periodensperren, Prüfungen und Tenant-Isolation

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
