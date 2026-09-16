# Entwicklungsstatus

| Phase | Status | Prüfergebnis |
|---|---|---|
| 1 Fundament | Lauffähiges Fundament | Architektur, Schema, Tenant-Kontext, Rechtebasis, Audit, Installer, Login und App-Shell vorhanden |
| 2 Mitglieder und Organisation | In Umsetzung, erster produktiver Funktionsblock | Mitgliederstammdaten, Status, Archiv, Mehrfachmitgliedschaften, Organisationstypen, Gliederungsbaum, Verschieben von Einheiten, Rechte und Dashboard-Kennzahlen vorhanden |
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
- Mitgliedsstatus, Eintritt, Austritt, Kontaktdaten und interne Notizen
- Suche und Filter nach Status und Organisationseinheit
- Archivierung und Wiederherstellung
- beliebig viele Mitgliedschaften einer Person in verschiedenen Gliederungen
- frei definierbare Organisationstypen
- hierarchische Vereine/Verbände per Closure Table
- sichere Verschiebung von Gliederungen inklusive Zyklusprüfung
- Phase-2-Permissions und Administratorrolle
- echte Mitglieder- und Organisationskennzahlen im Dashboard
- Featuretests für Tenant-Isolation, Mitgliederanlage und Organisationshierarchie

Noch offen innerhalb Phase 2 sind insbesondere erweiterte Mitgliedsattribute, Serien-/Massenbearbeitung, Im-/Export, Dublettenprüfung, Haushalte/Familien, Funktionen/Ämter und weitergehende Organisationsstammdaten.
