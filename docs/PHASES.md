# Entwicklungsstatus

| Phase | Status | Prüfergebnis |
|---|---|---|
| 1 Fundament | Lauffähiges Fundament | Architektur, Schema, Tenant-Kontext, Rechtebasis, Audit, Installer, Login und App-Shell vorhanden |
| 2 Mitglieder und Organisation | In Umsetzung, zweiter produktiver Funktionsblock | Mitgliederstammdaten, Mehrfachmitgliedschaften, Mitgliedsarten, Haushalte, Funktionen/Ämter, Dublettenprüfung, CSV-Im-/Export, Organisationsbaum, Rechte und Dashboard-Kennzahlen vorhanden |
| 3 Verbandsarbeit | Offen | – |
| 4 Veranstaltungen und Kommunikation | Offen | – |
| 5 Dokumentengenerator | Offen | – |
| 6 Formulare und Workflows | Offen | – |
| 7 Beiträge und Verwaltung | Offen | – |
| 8 SaaS-Ausbau | Offen | Tenant-Kern vorhanden, Tarife/Abrechnung noch offen |
| 9 Stabilisierung | Offen | Releasepaket, Updatekanal und vollständige Abnahme noch offen |

## Definition „Phase fertig“

Eine Phase wird erst als fertig markiert, wenn Migrationen, Autorisierung, UI, Fehlerzustände, relevante Featuretests, Dokumentation und ein installierbarer Zwischenstand gemeinsam vorliegen. Ein Schema oder eine sichtbare Schaltfläche allein zählt nicht als fertiges Modul.

## Phase 2 – aktueller Umfang

Bereits umgesetzt:

- tenant-sichere Mitgliederprofile auf Basis zentraler Personen
- automatische oder manuelle Mitgliedsnummern
- Mitgliedsstatus, Eintritt, Austritt, umfangreiche Kontakt- und Personendaten, Notfallkontakt, Kommunikationspräferenz, Schlagworte und interne Notizen
- Suche und Filter nach Status, Organisationseinheit und Mitgliedsart
- Archivierung und Wiederherstellung
- beliebig viele Mitgliedschaften einer Person in verschiedenen Gliederungen
- frei konfigurierbare Mitgliedsarten mit zentralem Stammdatenkatalog
- Haushalte/Familien mit Beziehungsangabe und Hauptkontakt
- frei konfigurierbarer Funktions-/Ämterkatalog mit zeitlicher und organisationsbezogener Zuweisung
- Dublettenprüfung bei manueller Neuanlage mit expliziter Übersteuerungsmöglichkeit
- CSV-Export der Mitgliederdaten
- robuster CSV-Import mit Semikolon-/Komma-Erkennung, deutschen und ISO-Datumsformaten, E-Mail-/Mitgliedsnummernprüfung, Dublettenüberspringung und Zuordnung vorhandener Organisations-/Mitgliedsstammdaten
- frei definierbare Organisationstypen
- hierarchische Vereine/Verbände per Closure Table
- sichere Verschiebung von Gliederungen inklusive Zyklusprüfung
- Phase-2-Permissions und Administratorrolle
- Installer-Provisioning der Organisations-, Mitglieder- und Funktionsstammdaten für neue Mandanten
- echte Mitglieder- und Organisationskennzahlen im Dashboard
- Featuretests für Tenant-Isolation, Mitgliederanlage, Organisationshierarchie, Dublettenprüfung, Haushalte, Funktionen und CSV-Import

Noch offen innerhalb Phase 2 sind insbesondere:

- Serien-/Massenbearbeitung von Mitgliedern
- frei definierbare benutzerdefinierte Mitgliederfelder
- weitergehende Organisationsstammdaten und Organisationsdetailseiten
- Haushalts-/Familienübersichten außerhalb des einzelnen Mitgliedsprofils
- komfortable Importvorschau mit Spaltenmapping und Fehlerprotokoll zum Download
- erweiterte Auswertungen und Segmentfilter
