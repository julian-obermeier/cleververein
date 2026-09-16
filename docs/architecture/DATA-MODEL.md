# Datenmodell – Phase 1

```mermaid
erDiagram
    PERSONS ||--o| USERS : "besitzt optional"
    USERS }o--o{ TENANTS : "tenant_user"
    TENANTS ||--o{ ORGANIZATION_TYPES : definiert
    TENANTS ||--o{ ORGANIZATION_UNITS : besitzt
    ORGANIZATION_UNITS ||--o{ ORGANIZATION_UNITS : parent
    ORGANIZATION_UNITS ||--o{ ORGANIZATION_CLOSURE : ancestor
    USERS ||--o{ ROLE_ASSIGNMENTS : erhält
    ROLES ||--o{ ROLE_ASSIGNMENTS : wird_vergeben
    ROLES }o--o{ PERMISSIONS : bündelt
    USERS ||--o{ PERMISSION_ASSIGNMENTS : erhält_direkt
    TENANTS ||--o{ AUDIT_LOGS : protokolliert
```

Alle fachlichen Tabellen der späteren Module erhalten `tenant_id`, passende zusammengesetzte Indizes und serverseitige Tenant-Prüfung. Globale Tabellen sind nur zulässig, wenn sie nachweislich keine Mandantendaten enthalten, etwa das technische Berechtigungsregister.

IDs für interne Beziehungen sind numerisch. Nach außen verwendete zentrale Datensätze erhalten zusätzlich eine nicht erratbare UUID (`public_id`). Dokumentdateien werden später mandantenbezogen außerhalb des Webroots gespeichert und ausschließlich über autorisierte Controller ausgeliefert.
