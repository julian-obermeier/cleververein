# Architektur

## Leitentscheidung

cleververein ist ein modularer Monolith. Fachmodule teilen sich Authentifizierung, Tenant-Auflösung, Autorisierung, Audit, Benachrichtigungen und Infrastruktur, bleiben aber über Services, Events, Jobs, Policies und eigene Namespaces fachlich getrennt. Microservices sind für Shared Hosting weder nötig noch sinnvoll.

## Mandantenkontext

1. Nach der Anmeldung wird der aktive Mandant serverseitig aus Benutzerzuordnung und Sitzung ermittelt.
2. `ResolveTenant` akzeptiert keinen ungeprüften Mandanten aus Formularfeldern.
3. `TenantContext` lebt ausschließlich für den aktuellen Request.
4. `BelongsToTenant` ergänzt Leseabfragen um `tenant_id` und setzt die ID beim Erstellen automatisch.
5. Service-Methoden prüfen Beziehungen erneut, bevor fremde IDs verwendet werden.
6. Globale Administratoren benötigen einen expliziten Supportkontext; der Einstieg wird auditiert und in der UI sichtbar gemacht.

Eine zentrale Datenbank wurde gewählt, weil sie Updates, Shared-Hosting-Betrieb und mandantenübergreifende SaaS-Administration vereinfacht. Die Isolation ist deshalb eine sicherheitskritische Invariante und wird mit Angriffstests abgesichert.

## Organisationsbaum

`organization_units.parent_id` liefert die direkte Navigation. `organization_closure` speichert alle Vorfahren-/Nachfahrenbeziehungen einschließlich Selbstbeziehung (`depth = 0`). Dadurch sind Untergliederungsabfragen unabhängig von der Baumtiefe performant. Änderungen am Baum gehören ausschließlich in `OrganizationTreeService` und laufen in einer Transaktion.

## Identitäten

- `persons`: natürliche Person, unabhängig von Login oder Mitgliedschaft
- `users`: genau ein technisches Konto, optional mit einer Person verknüpft
- `tenant_user`: Zulassung eines Kontos zu einem Mandanten
- künftige `memberships`: fachliche Mitgliedschaft einer Person in einer Organisationseinheit
- künftige `office_assignments`: zeitlich begrenzte Funktion derselben Person

Es entstehen keine separaten Kandidaten-, Teilnehmer- oder Funktionsträgerstammsätze.

## Autorisierung

Rechte besitzen technische Schlüssel wie `members.view`. Rollen bündeln Rechte. Rollen- und Einzelrechtsvergaben können mandantenweit oder auf eine Organisationseinheit begrenzt werden, optional inklusive Untergliederungen und zeitlicher Gültigkeit. Ein direktes Verbot hat Vorrang vor einer direkten Erlaubnis; Policies bleiben für objektbezogene Regeln zuständig.

## Hintergrundarbeit

Laravel-Datenbankjobs werden in kurzen Batches durch einen Cronjob verarbeitet. Jeder spätere Massenauftrag besitzt Fortschritt, Sperre, Wiederholungen und Fortsetzungspunkte. Es gibt weder Redis- noch Dauerworker-Zwang.

## Frontend

Blade rendert semantisches HTML. Tailwind erzeugt vorkompilierte Assets; JavaScript verbessert Interaktionen progressiv. Produktion benötigt keinen Node-Prozess. Externe Schriften werden nicht geladen. Das visuelle Referenzdesign wurde außerhalb des auslieferbaren Quellcodes erstellt und in Design-Tokens sowie Komponentenregeln übertragen.
