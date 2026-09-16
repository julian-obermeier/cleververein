# Datenschutz- und Sicherheitskonzept – Grundlage

- Tenant-Zugriff wird serverseitig aus dem authentifizierten Benutzer abgeleitet.
- Mandantenmodelle verwenden automatische Scopes und setzen `tenant_id` beim Erstellen selbst.
- Fremdschlüssel aus Requests werden in Services erneut gegen den aktiven Tenant geprüft.
- Superadministration allein gewährt keinen unsichtbaren Mandantenzugriff; Supportmodus ist explizit, sichtbar und protokolliert.
- Passwörter verwenden Laravels konfigurierten Hash-Treiber; Klartext wird nie gespeichert.
- Login besitzt Rate Limiting, generische Fehlermeldung und Session-Regeneration.
- CSRF-Schutz, serverseitige Validierung und Blade-Escaping sind standardmäßig aktiv.
- Audit-Einträge sind über die Anwendung nicht änderbar; sensible Werte sollen vor Protokollierung reduziert werden.
- Produktivbetrieb zeigt weder Stacktraces noch Umgebungsvariablen.
- Dateien werden in späteren Phasen außerhalb öffentlicher Pfade gespeichert und nach Policy-Prüfung gestreamt.
- Zwei-Faktor-Felder sind vorbereitet; die vollständige Aktivierung mit Recovery-Codes gehört zur nächsten Authentifizierungshärtung.

Vor einem Produktionsrelease folgen Threat Model, Datenschutz-Folgenabschätzungs-Checkliste, Restore-Test, Upload-Härtung, CSP/HSTS-Konfiguration, Abhängigkeitsprüfung und externer Penetrationstest.
