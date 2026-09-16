# Entwicklungsstatus

| Phase | Status | Prüfergebnis |
|---|---|---|
| 1 Fundament | Lauffähiges Fundament | Architektur, Schema, Tenant-Kontext, Rechtebasis, Audit, Installer, Login und App-Shell vorhanden |
| 2 Mitglieder und Organisation | Weit fortgeschritten | Mitglieder, Mehrfachmitgliedschaften, Mitgliedsarten, Haushalte, Ämter, CRM, Tags, Segmente, Zusatzfelder, Dokumente, Kommunikation, Im-/Export und Organisationsbaum vorhanden |
| 3 Verbandsarbeit | Offen | – |
| 4 Veranstaltungen und Kommunikation | Teilweise vorbereitet | Kommunikationshistorie je Mitglied vorhanden; Veranstaltungen und zentrale Kommunikation noch offen |
| 5 Dokumentengenerator | Erster produktiver Funktionsblock | Mandantenfähige Vorlagen, visueller Editor, Platzhalter, PDF-Vorschau, private Ablage, Historie, Rechte und Featuretests vorhanden |
| 6 Formulare und Workflows | Offen | – |
| 7 Beiträge und Verwaltung | In Umsetzung, vierter produktiver Funktionsblock | Beiträge, Rechnungen, Finanzdokumente, SEPA, Bankabgleich, Journal, Belege, Kassenbuch, Abschlüsse, Periodensperren, Prüfungen und Berichte vorhanden |
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
- Haushalts-/Familienbeiträge mit einmaliger Abrechnung je Haushalt/Beitragssatz/Jahr und Hauptkontakt als Rechnungsempfänger
- Rechnungsentwürfe ohne Nummer und verbindliches Ausstellen mit mandantenbezogener Jahressequenz
- unveränderlicher Empfänger-Snapshot beim Ausstellen einer Rechnung
- Rechnungspositionen, Netto-/Steuer-/Bruttobeträge und offene Posten
- private Rechnungs-PDFs mit berechtigungsgeprüftem Download
- Zahlungseingänge mit automatischer Statusaktualisierung und Schutz vor unkontrollierten Überzahlungen
- Gutschriften mit eigener GS-Jahressequenz und optionalem Vollstorno der Ursprungsrechnung
- private Gutschrift-PDFs
- verschlüsselte SEPA-Mandate mit maskierter IBAN-Anzeige
- Finanzstammdaten mit verschlüsselter Gläubiger-IBAN/BIC und strukturierter Anschrift
- SEPA-Core-Lastschriftläufe als pain.008.001.08 mit getrennten FRST-/RCUR-Blöcken
- expliziter SEPA-Lebenszyklus: erzeugt, exportiert und als eingereicht markiert
- private Speicherung der SEPA-XML-Dateien
- CSV-Bankimport mit Datei-/Umsatz-Dublettenschutz
- automatische Zahlungszuordnung über Rechnungsnummer sowie ergänzend Mitgliedsnummer und exakten Betrag
- manuelle Zuordnung und bewusstes Ignorieren ungeklärter Bankumsätze
- Mahnstufen und Mahngebühren als separate Historie
- private Mahnungs-PDFs
- Finanz-Cockpit plus separater Bereich „Finanzoperationen“ für sensible Stammdaten, SEPA und Bankabgleich
- Finanzkonten für Bank, Kasse, Verrechnung und frei definierbare weitere Konten
- frei definierbare Einnahmen- und Ausgabenkategorien mit optionalem Standard-Steuersatz
- unveränderliches Finanzjournal mit fortlaufender BU-Jahressequenz
- manuelle Einnahmen-/Ausgabenbuchungen mit Netto-, Steuer- und Bruttobeträgen
- Korrektur manueller Buchungen ausschließlich über nachvollziehbare Gegenbuchungen statt Löschung
- automatische Journalbuchung neuer Rechnungzahlungen mit Dublettenschutz je Zahlung
- bestehende Rechnungzahlungen werden beim Update einmalig in das Journal übernommen
- automatische Zuordnung von Bankimport-Zahlungen zum Journal
- Kontostände inklusive Eröffnungsbestand
- Jahres-, Monats- und Kategorieauswertungen
- filterbarer CSV-Journalexport
- private Belegablage direkt an Finanzbuchungen mit berechtigungsgeprüftem Download
- Belege werden bei Korrekturen als ungültig markiert statt physisch gelöscht
- dedizierter Kassenbereich mit Kassenbuch und CSV-Export
- Kassenabschlüsse mit Systembestand, gezähltem Istbestand und Differenz
- abgeschlossene Kassenzeiträume verhindern serverseitig nachträgliche Buchungen
- globale Periodensperren mit protokollierter Wiederöffnung
- dokumentierte Kassenprüfungen mit Prüfzeitraum, Buchungsanzahl, Soll-/Istbestand, Differenz und Feststellungen
- eigene Rechte für Finanzbuchungen, Berichte, Belege, Kasse, Periodensperren und Kassenprüfung
- Fresh-Install- und neue-Tenant-taugliche Initialisierung der Standardkonten/-kategorien
- Tenant-Isolation, Audit-Logging und Featuretests für Journal, Belege und Kassenkontrollen

Nächste Ausbaustufen:

- Rücklastschriften und Erstattungs-/Guthabenworkflow
- CAMT-Import zusätzlich zum generischen Bank-CSV
- Bank-spezifische SEPA-Validierung/XSD-Prüfung vor Export
- Spenden/Zuwendungsbescheinigungen
- weitergehende Jahresberichte und Exporte für Steuerberatung/DATEV-nahe Weiterverarbeitung
