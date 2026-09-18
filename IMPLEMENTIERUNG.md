# Implementierungs- und Prüfstand

Stand: 18.09.2026 · Version 1.0.0 · Schema 001_initial

## Lieferumfang

Die Muss-Funktionen F-01 bis F-09, F-12 bis F-14, F-16 und F-18 bis F-22 sowie die Soll-Funktionen F-10/F-11 sind implementiert. Die optionalen Funktionen Stoppuhr (F-15) und PDF (F-17) sind nicht enthalten. Bedienung, Installation und Grenzen stehen in `README.md`.

Die Anwendung läuft als drei Dienste mit lokalem PHP-Code, nativem JavaScript und bereits kompiliertem Tailwind-CSS. Es gibt keine Frameworkinstallation, kein Composer, keine Node-Laufzeit und keinen CSS-Build beim Deployment.

Gegenüber dem konzeptionellen Datenmodell liegen Arbeitszeiten und Korrekturen gemeinsam in `time_entries`, unterschieden über `kind`. Dadurch verwenden Filter, Summen und CSV denselben Datenbestand. Korrekturen besitzen `original_id` und Zielwerte. `submission_keys` speichert wiederholbare Speicherergebnisse; `auth_version` widerruft Sitzungen nach Benutzeränderungen. Alle Schreibtransaktionen sperren die Firmenzeile; diese einfache Serialisierung ist bewusst auf ein kleines Team zugeschnitten.

## Durchgeführte Prüfungen

| Prüfung | Ergebnis |
|---|---|
| Docker-Build und Start | Nginx, PHP-FPM und MySQL erfolgreich gestartet; alle drei Healthchecks erfolgreich |
| Tatsächliche PHP-/MySQL-Version | PHP 8.5.10, MySQL 8.4.11 |
| PHP-Syntax | Alle 13 ausgelieferten PHP-Dateien ohne Syntaxfehler |
| JavaScript-Syntax | Native JavaScript-Datei besteht `node --check` |
| Tailwind | Lokale CSS-Datei mit Standalone CLI 4.3.0 erzeugt |
| MySQL-Fachlogik | 48 Assertions in `tests/integration.php` bestanden |
| HTTP-Schnittstellen | 25 Assertions in `tests/http.mjs` bestanden, auch nach SQL-Wiederherstellung |
| Datenbankrechte | Vier Prüfungen in `tests/grants.php` bestanden: Historie nicht änder-/löschbar, kein CREATE-Recht, kein Root-Secret im PHP-Container |
| Installation | Leeren Testordner initialisiert; erneuter Setup-Aufruf lässt vorhandene Konfiguration und Secrets unverändert |
| Browser | Lokale Anmeldung, schnelle Zeiterfassung, mobile Eingabe, Abrechnung, Freigabedialog und Kundensicht geprüft; 390-/360-Pixel-Ansicht ohne horizontales Seiten-Scrolling, keine Warnungen/Fehler im geprüften Browserprotokoll |
| Datenbanksicherung | Konsistenter SQL-Dump, Kopie auf den Host und Import in angehaltene separate Testumgebung erfolgreich |
| Lokale Nutzdaten | Ein frisch angelegter Administrator; null Arbeitszeiteinträge. Beispieldaten und Lastdaten ausschließlich im separaten Testprojekt |

Die Fachtests decken unter anderem historische Stundensätze, Überlappung von Satzperioden, Vorrang persönlicher Sätze, Cent-Rundung, fehlende Sätze, nicht abrechenbare Zeiten, Team-/Kundensicht, fremde Schreibzugriffe, unveränderliche Abrechnungen, Korrektursummen, atomare Stapelaktionen, veraltete Datensätze, den letzten Administrator und Rechtewiderruf ab. HTTP-Tests prüfen zusätzlich CSRF, Origin-Prüfung, geschützte Dateien, parallele Wiederholungsanfragen, Passwortwechselpflicht und Sitzungswiderruf.

Die Tests mit erweiterten DB-Rechten erzeugen ausschließlich eine zufällig benannte temporäre Datenbank. HTTP-, Browser-, Wiederherstellungs- und Lasttests verwenden das eigene Compose-Projekt `zeitwerk-tests` auf Port 18080. Bekannte Testpasswörter gehören ausschließlich zu dieser isolierten Umgebung.

## Gemessene lokale Leistung

50.000 zusätzlich erzeugte Einträge, zehn verschiedene gleichzeitig angemeldete Benutzer, drei Durchläufe mit jeweils zehn parallelen Berichtsabfragen und zehn parallelen Speicheranfragen:

| Operation | Anzahl | Mittelwert | Maximum |
|---|---:|---:|---:|
| Gefilterter Bericht einschließlich Gesamtsummen und Personensummen | 30 | 729 ms | 1.372 ms |
| Zeiteintrag speichern | 30 | 141 ms | 248 ms |

Das vorgeschlagene lokale Zwei-Sekunden-Ziel wurde in diesem Durchlauf erreicht. Gemessen wurde die vollständige HTTP-Antwort über Loopback unter Docker Desktop, ohne externen Proxy oder Internet-Latenz. Dies ist keine Zusicherung für einen anderen Server oder eine Dauerauslastung. Reproduktion: `tests/performance-fixtures.php` und `tests/performance.mjs`.

## Noch vor dem produktiven Einsatz zu prüfen

- Tatsächliche öffentliche Domain, Proxy-Quelladresse nach Docker-NAT, sichere Cookies über HTTPS, Forwarding-Header und eingeschränkte Erreichbarkeit des internen HTTP-Ports. Die vorhandene Proxy-/Let's-Encrypt-Umgebung wurde nicht verändert und konnte mangels Zielkonfiguration nicht Ende zu Ende getestet werden.
- Eigene Stundensätze, lokale Benutzerkonten und konkrete Rollenverteilung eintragen.
- Regelmäßige Sicherung auf ein getrenntes Ziel und eine Wiederherstellung auf dem tatsächlichen Zielhost organisieren; RPO von 24 Stunden und Wiederherstellung innerhalb eines Arbeitstags hängen vom Betrieb ab.
- CSV in der tatsächlich verwendeten deutschsprachigen Excel-Version öffnen. Das Format UTF-8/BOM/Semikolon/Dezimalkomma sowie die HTTP-Ausgabe wurden technisch geprüft; Excel selbst wurde nicht gestartet.
- Das 20-Sekunden-Bedienziel mit realen Teammitgliedern prüfen; ein automatisierter Browserdurchlauf ersetzt keine Anwendererprobung.
- Aufbewahrungs-/Löschregeln festlegen. Soft-deletete Zeiten bleiben für die Historie gespeichert. Es gibt derzeit keine automatische fachliche Datenbereinigung.

Getestet wurde im Chromium-basierten In-app-Browser. Eine vollständige Browser-/Assistenztechnik-Matrix und ein unabhängiges Sicherheitsaudit sind nicht Bestandteil dieses Prüfstands.
