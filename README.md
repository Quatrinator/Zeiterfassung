# Zeitwerk – gemeinsame IT-Zeiterfassung

Kleine, deutschsprachige Webanwendung für ein IT-Team und eine externe Firma. PHP ohne Framework, natives JavaScript, MySQL und lokal ausgeliefertes Tailwind-CSS. Im Betrieb laufen genau drei Container: Nginx, PHP-FPM und MySQL. Der vorhandene Reverse Proxy übernimmt HTTPS und Let's Encrypt.

## Funktionen

- Schnellerfassung mit heutigem Datum, Dauer-Schaltflächen, Beschreibung, Kategorie und Abrechenbarkeit; letzte Tätigkeit übernehmen.
- Gemeinsame Tätigkeitsvorlagen im Dropdown. Eine Auswahl reicht ohne Kommentar; optionaler Freitext ergänzt die Vorlage. Ohne Vorlage bleibt die eigene Beschreibung verpflichtend. Sechs typische IT-Tätigkeiten sind vorbelegt.
- Alle internen Benutzer sehen die Tätigkeiten des gesamten Teams. Eigene Entwürfe sind bearbeitbar; fremde nur mit entsprechender Berechtigung.
- Lokale Benutzerkonten, Administratoren, eigene Rollen und Zusatzrechte. Keine Selbstregistrierung. Initialpasswörter gelten 24 Stunden und müssen geändert werden.
- Stundensätze pro Firma oder Person mit Gültigkeitszeiträumen; persönliche Sätze haben Vorrang. Gespeicherte Sätze bleiben historisch erhalten.
- Entwurf → Freigegeben → Abgerechnet, einschließlich Stapelaktionen und optionaler Rechnungsreferenz. Abgerechnete Originale bleiben unverändert; Korrekturen erscheinen als gesonderte Differenzpositionen.
- Kundenkonten sehen ausschließlich veröffentlichte Leistungen samt Beträgen. Sie können weder Daten verändern noch interne Stammdaten lesen.
- Filter, Summen, Auswertung nach Person, CSV-Export und Änderungshistorie.
- Responsive Oberfläche, CSRF-Schutz, Argon2id, Login-Begrenzung, serverseitige Rechte, Versionskontrolle und Schutz vor doppeltem Speichern.

Eine Firma und EUR netto sind bewusst festgelegt. Die Anwendung erzeugt Leistungsnachweise und Abrechnungsgrundlagen, keine Rechnungsdokumente. Stoppuhr, PDF-Erzeugung, Anhänge, E-Mail-Versand und mehrere Mandanten gehören nicht zur ersten Version.

## Voraussetzungen

Auf dem Zielhost werden Docker Engine mit Compose v2.24.4 oder neuer bzw. Docker Desktop benötigt. Linux-Container verwenden. Es müssen weder PHP, MySQL, Composer, Node.js, npm noch Tailwind auf dem Webserver installiert werden. Für den ersten Image-Build benötigt Docker Internetzugriff. Das fertige CSS ist mitgeliefert; die Website lädt keine externen Schriftarten, Skripte oder CDN-Dateien.

Alle folgenden Befehle im Projektverzeichnis ausführen. PowerShell und POSIX-Shell verstehen die Installationsbefehle gleichermaßen.

## Installation

```sh
docker compose build php
docker run --rm --user root --mount "type=bind,source=${PWD},target=/setup" zeitwerk-php:1.0 php /setup/bin/setup.php
```

Das Setup erzeugt zufällige Datenbankpasswörter in `secrets/` und kopiert `.env.example` nach `.env`. Bestehende Dateien bleiben erhalten. `${PWD}` steht für den absoluten aktuellen Projektpfad in PowerShell und POSIX-Shell. Unter Linux ist das erzeugte Secrets-Verzeichnis nur für seinen Besitzer zugänglich; Container erhalten ausschließlich die benötigten einzelnen Dateien. Unter Windows den Projektordner über NTFS-Rechte auf die Betreiber beschränken.

Für einen lokalen HTTP-Test stattdessen beim ersten Setup `--local` anhängen:

```sh
docker run --rm --user root --mount "type=bind,source=${PWD},target=/setup" zeitwerk-php:1.0 php /setup/bin/setup.php --local
```

Vor dem Produktivstart `.env` setzen:

```dotenv
APP_URL=https://zeit.deine-domain.de
APP_NAME=Zeitwerk
COMPANY_NAME=Deine externe Firma
APP_ENV=production
COOKIE_SECURE=1
HTTP_BIND=127.0.0.1
HTTP_PORT=8080
TRUSTED_PROXIES=127.0.0.1/32
```

`APP_URL` muss der tatsächlichen Adresse entsprechen, ohne abschließenden Schrägstrich. Die Anwendung wird an der Wurzel einer eigenen Domain/Subdomain betrieben. Produktionsbetrieb verlangt HTTPS und sichere Cookies, auch wenn die Verbindung zwischen Proxy und Nginx HTTP verwendet.

```sh
docker compose up -d --wait
docker compose exec php php bin/create-admin.php
```

Benutzernamen und Anzeigenamen eingeben. Das einmal angezeigte Initialpasswort sicher übernehmen, anmelden und sofort ändern. Danach unter **Stundensätze** den Firmensatz und unter **Benutzer** die Team- und Kundenkonten anlegen. Ein fehlender Satz verhindert die Freigabe abrechenbarer Zeiten, nicht deren Erfassung.

Das erste Konto kann nur bei leerer Benutzertabelle angelegt werden. Weitere Administratoren werden über die Oberfläche zugewiesen. Falls das erste Passwort abgelaufen ist oder kein Administrator mehr sein Passwort kennt, erlaubt der kontrollierte Hostzugang:

```sh
docker compose exec php php bin/reset-admin.php BENUTZERNAME
```

Dies setzt ausschließlich ein bestehendes aktives Administratorkonto zurück, beendet dessen alte Sitzungen und protokolliert den Vorgang.

## Vorhandenen Reverse Proxy anschließen

Der Proxy leitet die gesamte Domain an den internen Nginx-Port `8080` weiter und erhält den ursprünglichen `Host`-Header. Ein Beispiel für einen bestehenden Nginx-Proxy steht in `docker/proxy.example.conf`; dessen Zertifikatskonfiguration bleibt beim Proxy.

- Proxy als Dienst auf demselben Host: Loopback-Bind `127.0.0.1` verwenden.
- Proxy auf anderem Host: `HTTP_BIND` auf die private Host-IP setzen und den Port in der Firewall ausschließlich für den Proxy freigeben.
- Proxy in einem anderen Docker-Container: dessen `127.0.0.1` zeigt auf ihn selbst. Ein gemeinsames externes Docker-Netz ausschließlich an den Anwendungs-Nginx anbinden oder eine vom Proxy erreichbare private Hostadresse verwenden. PHP und MySQL bleiben intern.

`TRUSTED_PROXIES` enthält die tatsächlichen Quelladressen/CIDR-Netze der vertrauenswürdigen Proxy-Hops, kommagetrennt. Docker-NAT kann dabei die sichtbare Quelladresse verändern. Diese Adresse vor Produktivbetrieb prüfen. Keine pauschalen Freigaben wie `0.0.0.0/0` setzen. `X-Forwarded-For` wird nur von diesen Absendern ausgewertet. Der öffentliche Proxy muss vom Client gelieferte Forwarding-Header ersetzen; bei mehreren Proxys die vollständige vertrauenswürdige Kette konfigurieren. HTTPS wird fest über die Anwendungskonfiguration bestimmt, nicht anhand beliebiger Client-Header.

MySQL hat keinen veröffentlichten Host-Port. Der PHP-Container bekommt nur das eingeschränkte Anwendungspasswort, kein MySQL-Root-Passwort. Der Datenbankbenutzer darf die Änderungshistorie hinzufügen und lesen, aber nicht ändern oder löschen.

## Bedienung und Rechte

| Rolle | Arbeitszeiten | Geldbeträge | Verwaltung |
|---|---|---|---|
| Teammitglied | Alle internen Zeiten lesen; eigene Entwürfe erfassen/ändern | Standardmäßig verborgen | Eigenes Passwort |
| Abrechnung | Alle Zeiten, fremde Entwürfe bearbeiten, Freigabe/Abrechnung/Korrektur | Ja | Stundensätze und Historie |
| Administrator | Alle Funktionen | Ja | Benutzer, Rollen, Rechte |
| Kunde | Nur freigegebene und abgerechnete Zeiten | Ja | Eigenes Passwort |

Die Rollen Teammitglied und Abrechnung können angepasst und eigene Rollen erstellt werden. Administrator und Kunde bleiben geschützt. Zusätzliche Rechte pro Benutzer werden mit Rollenrechten vereinigt. Der letzte aktive Administrator kann nicht entfernt werden. Geldabhängige Rechte schließen automatisch `finance.view` ein; auch die Historie erfordert es, weil sie alte Beträge enthält.

Ein normaler Eintrag umfasst 1–1440 ganze Minuten. Über 12 Stunden pro Person/Tag erfolgt ein Hinweis. Leistungsdaten dürfen nicht in der Zukunft liegen. Ein Satz gilt ab seinem Startdatum einschließlich und bis zum Enddatum ausschließlich. Beträge werden je Position auf Cent gerundet; Summen addieren diese gerundeten Positionen.

Geänderte Stundensätze verändern keine alten Einträge. **Neu berechnen** ordnet ausgewählten Entwürfen den zum Leistungsdatum gültigen Satz bewusst neu zu. Änderungen von Datum oder Abrechenbarkeit lösen diese Zuordnung ebenfalls aus. Freigegebene Einträge sind für die Bearbeitung erst mit Begründung zurückzuziehen. Abgerechnete Positionen sind unveränderlich: **Korrigieren** erfasst den gewünschten neuen Gesamtstand und erzeugt nur die Differenz zum bisher veröffentlichten Stand. Kunden sehen die Korrektur nach ihrer Freigabe zusätzlich zum Original. Deshalb beim Auswerten alle Positionen summieren, Korrekturen nicht auslassen.

CSV übernimmt dieselben Filter und dieselbe Sichtbarkeit wie die Liste, über alle Seiten hinweg. UTF-8 mit BOM, Semikolon, Dezimalkomma; Zeitwerte in Minuten. Beschreibungstexte werden gegen Tabellenformeln abgesichert. Datumszuordnung erfolgt in Europe/Berlin, technische Zeitstempel werden in UTC gespeichert.

## Tätigkeitsvorlagen

Unter **Zeit erfassen → Tätigkeit auswählen** eine Vorlage wählen und bei Bedarf ergänzen. Gespeichert wird ein eigenständiger Text, beispielsweise „Backup prüfen – Sicherung vom Wochenende“. Für eine freie Tätigkeit „Eigene Tätigkeit / keine Vorlage“ wählen und die Beschreibung ausfüllen. Auswahl und Ergänzung dürfen zusammen höchstens 500 Zeichen lang sein.

Administratoren können unter **Tätigkeitsvorlagen** gemeinsame Vorlagen hinzufügen und nach Bestätigung löschen. Das zusätzliche Recht `templates.manage` lässt sich über Rollen oder Benutzerrechte delegieren und erteilt keine Betragsansicht. Bestehende Einträge und Exporte behalten ihren ursprünglichen Text nach dem Löschen. Beim Bearbeiten wird eine noch vorhandene passende Vorlage wieder ausgewählt; bei gelöschten Vorlagen bleibt die komplette Beschreibung als Freitext erhalten.

## Sichern

Die MySQL-Daten liegen im Docker-Volume `zeitwerk_mysql_data`, nicht im Quellcodeordner. Ein Kopieren des Ordners allein nimmt bestehende Daten **nicht** mit. Regelmäßig SQL-Sicherung, passende Programmversion und Konfiguration getrennt vom Host sichern. SQL-Dateien enthalten Benutzerdaten und Passwort-Hashes und müssen entsprechend geschützt werden.

```sh
docker compose exec -T mysql sh /opt/zeitwerk/backup.sh
docker compose cp mysql:/tmp/zeitwerk-backup.sql ./zeitwerk-backup.sql
docker compose exec -T mysql rm /tmp/zeitwerk-backup.sql
```

Die Sicherung verwendet `mysqldump --single-transaction`; der Betrieb kann weiterlaufen. Die Datei anschließend datiert in ein geschütztes Backup-Ziel verschieben. Die Befehle vermeiden insbesondere PowerShell-Umkodierung durch `>`-Umleitung. Keine zwei Sicherungen gleichzeitig ausführen, da dieselbe temporäre Datei verwendet wird.

## Wiederherstellen oder auf einen anderen Host umziehen

1. Projektdateien auf den Zielhost kopieren, Setup ausführen, `.env` und Proxy konfigurieren. Neue Datenbankpasswörter sind bei einer neuen Datenbank möglich.
2. `docker compose up -d --wait` ausführen. Die leere Datenbank wird initialisiert. **Keinen neuen Admin** anlegen, wenn eine bestehende Sicherung eingespielt wird.
3. Vorhandene Daten des Zielsystems sichern. Der folgende Import ersetzt Tabelleninhalte. Die gesicherte Programmversion muss zum Schema passen.

```sh
docker compose stop nginx php
docker compose cp ./zeitwerk-backup.sql mysql:/tmp/zeitwerk-restore.sql
docker compose exec -T mysql sh /opt/zeitwerk/restore.sh --replace-database-contents
docker compose start php nginx
docker compose ps
```

4. Anmeldung, Personen, Zeitanzahl, Summen und einen Export prüfen. Bei einem Hostwechsel werden keine PHP-Sitzungen übernommen; Benutzer melden sich erneut an. Das alte System nach dem Umschalten nicht weiter beschreiben.

Keine laufenden MySQL-Volume-Dateien einfach kopieren. `docker compose down -v` löscht die persistente Datenbank und ist kein normaler Stopp-Befehl. Normal stoppen: `docker compose down` (ohne `-v`) oder `docker compose stop`.

## Wartung und Aufbau

```text
public/       Einziger Webroot: PHP-Einstieg, JS, fertiges CSS, Favicon
src/          Authentifizierung, Rechte, Zeiterfassung, Abrechnung, Berichte
templates/    HTML-Grundgerüst
database/     Schema, minimale DB-Rechte, Backup-/Restore-Skripte
docker/       PHP-Image und Nginx-Konfiguration
config/       Optionales config.local.php außerhalb des Webroots
bin/          CLI für Installation und Adminzugang
styles/       Tailwind-Quelldatei
tests/        Isolierte Fachlogik- und HTTP-Tests
```

Verwendet: PHP 8.5, MySQL 8.4, Nginx 1.28, Tailwind 4.3.0. Image-Tags bleiben innerhalb dieser Versionslinien beweglich, damit Sicherheitsupdates möglich sind; ein Image-Pull kann daher neuere Patchstände liefern. Für exakt reproduzierbare Installationen nach Prüfung der Umgebung Image-Digests fixieren. Keine automatischen Major-Upgrades.

Vor Updates sichern. Images gezielt aktualisieren und anschließend prüfen:

```sh
docker compose pull nginx mysql
docker compose build --pull php
docker compose up -d --wait
docker compose ps
docker compose logs --tail=100 php nginx mysql
```

Das initiale SQL läuft ausschließlich auf einem leeren MySQL-Volume. Version 1.1.0 enthält zusätzlich Schema `002_activity_templates`. Bei einer bestehenden Installation nach Sicherung und Kopieren der neuen Dateien einmal ausführen:

```sh
docker compose exec -T mysql sh /opt/zeitwerk/migrate.sh
```

Die Migration ergänzt Vorlagentabelle, Standardvorlagen und Rechte. Ein erneuter Aufruf überspringt bereits angewendete Änderungen; bewusst gelöschte Vorlagen werden nicht erneut angelegt. `schema.sql` auf einer produktiven Datenbank erneut auszuführen ist kein Updateverfahren. Bei Wiederverwendung bestehender Volumes die bestehenden Secrets aufbewahren: bloßes Ändern der Passwortdateien ändert keine MySQL-Benutzerpasswörter.

HTTP-Sitzungen liegen im separaten Volume und laufen nach acht Stunden Inaktivität ab. Eine einzige PHP-Instanz ist vorgesehen. Zugangsdaten gehören nicht ins Repository. `login_attempts` und `submission_keys` werden derzeit dauerhaft gespeichert; bei längerem Betrieb Datenwachstum beobachten. Eine Aufbewahrungs-/Bereinigungsroutine ist noch nicht enthalten. Protokolle der Container sind größenbegrenzt.

## Entwicklung und Prüfungen

`public/assets/css/app.css` ist fertig enthalten. Nur bei Änderungen an Tailwind-Klassen oder `styles/input.css` neu kompilieren: offiziellen [Tailwind Standalone CLI](https://tailwindcss.com/blog/standalone-cli) in Version 4.3.0 herunterladen und beispielsweise ausführen:

```sh
./tailwindcss -i ./styles/input.css -o ./public/assets/css/app.css --minify
```

Das Werkzeug ist ausschließlich für die Entwicklung nötig; kein weiterer Dienst. JavaScript wird ohne Build ausgeliefert. Betrieb und Deployment brauchen keinen Node-Prozess. Bei Änderungen die Asset-Version in `templates/shell.php` anheben, damit Browser neue Dateien laden.

Fachlogiktest (separate zufällig benannte Testdatenbank, wird danach entfernt):

```sh
docker compose run --rm --no-deps -v "${PWD}/tests:/var/www/app/tests:ro" -v "${PWD}/secrets/db_root_password.txt:/run/secrets/test_db_root:ro" php php tests/integration.php
```

Der Root-Zugang wird ausschließlich dem kurzlebigen Testcontainer zugeordnet. Diesen Test nur in einer Entwicklungsumgebung ausführen.

HTTP-/Browserprüfung mit vollständig separatem Docker-Projekt und festen **öffentlichen Testzugängen**:

```sh
docker compose -p zeitwerk-tests -f compose.yaml -f compose.test.yaml up -d --wait
docker compose -p zeitwerk-tests -f compose.yaml -f compose.test.yaml exec -T php php tests/fixtures.php
node tests/http.mjs
docker compose -p zeitwerk-tests -f compose.yaml -f compose.test.yaml exec -T php php tests/grants.php
```

Nur für diesen optionalen Entwicklertest ist Node.js erforderlich. Testoberfläche: `http://127.0.0.1:18080`; Konten `qa_admin`, `qa_member`, `qa_customer`, Passwort jeweils `LocalTestPassphrase!2026`. Niemals nach außen veröffentlichen. Fixtures nur in leerer Testdatenbank ausführen. Nach den Tests ausschließlich das Testprojekt entfernen:

```sh
docker compose -p zeitwerk-tests -f compose.yaml -f compose.test.yaml down -v
```

Der dokumentierte Prüfstand und verbleibende Betriebsprüfungen stehen in `IMPLEMENTIERUNG.md`.

Optionaler Lasttest vor dem Entfernen der Testumgebung:

```sh
docker compose -p zeitwerk-tests -f compose.yaml -f compose.test.yaml exec -T php php tests/performance-fixtures.php
node tests/performance.mjs
```

Er erzeugt 50.000 Einträge und zehn Testkonten ausschließlich im Testprojekt. Das Skript prüft drei Durchläufe paralleler Berichts- und Speicheranfragen.

Das fertige portable Paket wird unter `dist/zeitwerk-1.1.0.zip` bereitgestellt. Entwickler können es mit PowerShell 7 über `./bin/package.ps1` erneut erzeugen. Der Paketcheck schließt lokale Konfiguration, Initialzugang, Secrets und Entwicklungswerkzeuge aus. Ein produktiver Datenbankexport wird bewusst separat transportiert.
