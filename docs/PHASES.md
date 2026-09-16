# Entwicklungsstatus

| Phase | Status | Prüfergebnis |
|---|---|---|
| 1 Fundament | Lauffähiges Fundament | Architektur, Schema, Tenant-Kontext, Rechtebasis, Audit, Installer, Login und App-Shell vorhanden |
| 2 Mitglieder und Organisation | Weit fortgeschritten | Mitglieder, Mehrfachmitgliedschaften, Mitgliedsarten, Haushalte, Ämter, CRM, Tags, Segmente, Zusatzfelder, Dokumente, Kommunikation, Im-/Export und Organisationsbaum vorhanden |
| 3 Verbandsarbeit | Offen | – |
| 4 Veranstaltungen und Kommunikation | Teilweise vorbereitet | Kommunikationshistorie je Mitglied vorhanden; Veranstaltungen und zentrale Kommunikation noch offen |
| 5 Dokumentengenerator | Erster produktiver Funktionsblock | Mandantenfähige Vorlagen, visueller Editor, Platzhalter, PDF-Vorschau, private Ablage, Historie, Rechte und Featuretests vorhanden |
| 6 Formulare und Workflows | Offen | – |
| 7 Beiträge und Verwaltung | In Umsetzung, erster produktiver Funktionsblock | Beitragssätze/-regeln, individuelle Ausnahmen, Jahresbeitragslauf, Rechnungen, Zahlungseingänge, SEPA-Mandate und Mahnhistorie vorhanden |
| 8 SaaS-Ausbau | Offen | Tenant-Kern vorhanden, Tarif-/Aboverwaltung der SaaS-Plattform noch offen |
| 9 Stabilisierung | Laufend | CI prüft Vite-Build, Pint und PHPUnit; vollständige Release-/Updateabnahme noch offen |

## Definition „Phase fertig“

Eine Phase wird erst als fertig markiert, wenn Migrationen, Autorisierung, UI, Fehlerzustände, relevante Featuretests, Dokumentation und ein installierbarer Zwischenstand gemeinsam vorliegen. Ein Schema oder eine sichtbare Schaltfläche allein zählt nicht als fertiges Modul.

## Phase 2 – aktueller Umfang

Bereits umgesetzt:

- tenant-sichere Mitgliederprofile auf Basis zentraler Personen
- automatische oder manuelle Mitgliedsnummern
- Mitgliedsstatus, Eintritt, Austritt, Kontakt- und Personendaten, interne Notizen
- Suche und Filter nach Status, Organisationseinheit und Mitgliedsart
- Archivierung und Wiederherstellung
- beliebig viele Mitgliedschaften einer Person in verschiedenen Gliederungen
- frei konfigurierbare Mitgliedsarten
- Haushalte/Familien mit Beziehungsangabe und Hauptkontakt
- frei konfigurierbare Funktionen/Ämter mit Zeitraum und Organisationsbezug
- Dublettenprüfung bei manueller Neuanlage und Import
- CSV- und XLSX-Im-/Export inklusive Importvorlage
- Tags, dynamische Segmente und Massenaktionen
- benutzerdefinierte Mitgliedsfelder
- private Mitgliederdokumente
- Kommunikationshistorie
- vollständiger Audit-/Änderungsverlauf je Mitglied
- frei definierbare Organisationstypen und beliebig tiefe Organisationshierarchien per Closure Table
- sichere Verschiebung von Gliederungen inklusive Zyklusprüfung
- echte Mitglieder- und Organisationskennzahlen im Dashboard
- Featuretests für die zentralen Mitglieder-, CRM-, Haushalts-, Funktions- und Organisationsabläufe

Noch offen innerhalb Phase 2 sind insbesondere:

- weitergehende Organisationsstammdaten und Organisationsdetailseiten
- komfortable Importvorschau mit frei konfigurierbarem Spaltenmapping und Fehlerprotokoll zum Download
- weitergehende Auswertungen und Berichte

## Phase 5 – Dokumentengenerator

Bereits umgesetzt:

- tenant-sichere Dokumentvorlagen
- visueller Drag-&-Drop-Editor für Text und Linien
- A4, A5 und Letter sowie Hoch-/Querformat
- sichere serverseitige Layoutnormalisierung
- Mitglieds-, Vereins- und Datumsplatzhalter
- PDF-Vorschau und PDF-Erzeugung über Dompdf
- private Speicherung erzeugter PDFs außerhalb des öffentlichen Webroots
- Dokumenthistorie und berechtigungsgeprüfter Download
- direkte Übergabe eines Mitglieds aus dem CRM an den Generator
- Audit-Logging und eigene Dokumenten-Permissions
- Featuretests für Vorlagen, Platzhalter, Tenant-Isolation und PDF-Erzeugung

## Phase 7 – Beiträge & Verwaltung

Bereits umgesetzt:

- frei definierbare Beitragssätze mit monatlichem, vierteljährlichem, halbjährlichem, jährlichem oder einmaligem Intervall
- Beitragsregeln nach Mitgliedsart, Organisation und Alter mit Prioritäten
- individuelle Beitragsbeträge und zeitlich begrenzte Beitragsbefreiungen
- Jahresbeitragslauf mit Dublettenschutz je Mitglied/Beitragssatz/Jahr
- Rechnungsentwürfe ohne Nummer und verbindliches Ausstellen mit mandantenbezogener Jahressequenz
- Rechnungspositionen, Netto-/Steuer-/Bruttobeträge und offene Posten
- Zahlungseingänge mit automatischer Statusaktualisierung
- verschlüsselte SEPA-Mandate mit maskierter IBAN-Anzeige
- Mahnstufen und Mahngebühren als separate Historie
- Finanz-Cockpit mit offenen Forderungen, Überfälligkeit und Zahlungseingängen
- eigene Finance-Permissions und Audit-Logging

Nächste Ausbaustufen:

- Rechnungs-/Mahnungs-PDFs über den Dokumentengenerator
- SEPA-XML-Export und Lastschriftläufe
- Bankimport und automatische Zahlungszuordnung
- Storno-/Gutschriften-Workflow
- Haushalts-/Familienbeiträge und Beitragsdeckel
- Buchungskonten/Kategorien und umfangreichere Finanzberichte
