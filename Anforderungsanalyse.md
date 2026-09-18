# Anforderungsanalyse: Zeiterfassung für ein kleines IT-Team

Stand: 18.09.2026 · Version 1.3 · Frameworkfreies, kopierbares PHP-Webprojekt

## 1. Ausgangssituation und Ziel

Mehrere Personen übernehmen nebenberuflich IT-Arbeiten für eine externe Firma. Dafür soll eine kleine, gemeinsam genutzte Webseite entstehen. Das Erfassen einer Tätigkeit soll im Alltag so wenig Aufwand verursachen, dass Zeiten direkt und vollständig eingetragen werden.

Die Anwendung soll nachvollziehbar beantworten: **Wer hat wann wie lange an welcher Aufgabe gearbeitet und welcher Betrag ergibt sich daraus?** Die verantwortliche Person erhält eine interne Aufwandsübersicht und eine Abrechnungsgrundlage. Die externe Firma erhält einen eigenen Lesezugang zu den für sie freigegebenen Leistungen und Beträgen.

**Vom Auftraggeber vorgegeben:** Webanwendung, mehrere Mitarbeitende, eine externe Firma und eine möglichst einfache Zeiteingabe. Die Zeiten dienen sowohl der internen Übersicht als auch der Abrechnung einschließlich Stundensätzen und Beträgen. Die externe Firma erhält einen eigenen Lesezugang. Alle internen Teammitglieder sehen die Tätigkeiten und Arbeitszeiten der anderen. Die Anmeldung erfolgt über lokale Konten der Webseite; ein Admin legt Benutzer an und verteilt Rechte.

**Arbeitsannahmen für diesen Entwurf:** kleines Team mit zunächst bis zu zehn Personen; Nutzung am PC und Smartphone; Eintragung überwiegend nach Abschluss einer Tätigkeit; eine Firma und eine gemeinsame Zeitzone, Europe/Berlin. Beträge werden zunächst in Euro und netto geführt. Stundensätze sind Verkaufssätze gegenüber der externen Firma, keine Vergütungssätze der Mitarbeitenden. Diese Annahmen sind noch zu bestätigen.

**Technisch vorgegeben:** PHP ohne Anwendungsframework, natives JavaScript, MySQL und Tailwind CSS. PHP-FPM und MySQL laufen in jeweils eigenen Docker-Containern; ein Nginx-Container liefert die Webseite und statischen Dateien aus. Die fertige Anwendung soll als Dateipaket kopierbar sein und keine zusätzlichen Paketmanager oder Entwicklungswerkzeuge auf dem Zielserver benötigen. Ein vorhandener vorgeschalteter Proxy übernimmt HTTPS einschließlich Let's Encrypt.

**Noch offen:** konkrete Stundensätze und deren Gültigkeit, einheitliche oder personenbezogene Sätze, mögliche Rundungsregeln und der konkrete Hostingstandort. Die technische Ausgestaltung steht im ergänzenden Konzept.

## 2. Umfang und Prioritäten

Die erste Version umfasst Anmeldung, schnelle Erfassung, Korrektur, Stundensatzverwaltung, Betragsberechnung, Freigabe, internen Bericht, Kundenzugang und Export. Eine Kunden-, Projekt- oder Ticketauswahl ist für das Speichern eines Eintrags nicht erforderlich. Abrechnungsvorbereitung und vollständige Rechnungserstellung werden getrennt behandelt.

Die nachfolgenden Prioritäten sind Vorschläge:

- **Muss:** Bestandteil der ersten nutzbaren Version, im Folgenden MVP genannt.
- **Soll:** sinnvoll, sobald die grundlegende Nutzung funktioniert und der Bedarf bestätigt ist.
- **Kann:** spätere Erweiterung bei konkretem Bedarf.

## 3. Nutzende und Berechtigungen

| Rolle | Aufgaben und Rechte |
| --- | --- |
| Mitarbeitende | Eigene Zeiten erfassen; Tätigkeiten, Zeiten und Status aller internen Teammitglieder einschließlich Entwürfen ansehen und als Zeitbericht exportieren; eigene Entwürfe bearbeiten und löschen. Fremde Einträge bleiben ohne zusätzliches Recht unveränderbar. |
| Verantwortliche Person / Administration | Alle Zeiten und Beträge einsehen und exportieren; Stundensätze pflegen; Einträge prüfen, freigeben und als abgerechnet markieren; Konten verwalten; Korrekturen mit Änderungsgrund durchführen. |
| Externe Firma / Kunde | Freigegebene und abgerechnete Leistungen der eigenen Firma mit Datum, Anzeigename, Tätigkeit, Dauer, Stundensatz und Betrag lesen, filtern und exportieren; keinerlei Schreib- oder Verwaltungsrechte. |

Für ein kleines Team können Verantwortung und Administration bei derselben Person liegen. Alle aktiven internen Konten erhalten die gemeinsame Teamansicht. Die Sichtbarkeit von Arbeitszeiten erteilt kein Recht, fremde Einträge zu verändern. Stundensätze und Geldbeträge sind zunächst der verantwortlichen Person und im freigegebenen Bericht der externen Firma vorbehalten; der Admin kann internen Personen zusätzlich die Betragsansicht, Freigaberechte oder weitere Verwaltungsrechte zuweisen.

Vordefinierte Rollen erleichtern die Einrichtung. Der Admin kann Rollen zuweisen und fachliche Rechte aus einem festen Katalog vergeben. Kundenkonten bleiben unabhängig davon auf den lesenden Kundenbereich beschränkt. Der letzte aktive vollständige Admin darf nicht deaktiviert oder seiner Verwaltungsrechte beraubt werden.

Der Kunde sieht keine Entwürfe, Kontaktdaten der Benutzerkonten, internen Änderungsgründe oder Verwaltungsansichten. Sein Lesezugang erlaubt auch über direkte Datenanfragen keine Änderungen. Eine Freigabe durch die verantwortliche Person bedeutet die Veröffentlichung im Kundenportal, keine Bestätigung oder Zahlungsfreigabe durch den Kunden.

Das Deaktivieren eines Kontos beendet dessen Zugriff, erhält aber die zugeordneten Zeiteinträge und die bisherige Namenszuordnung.

## 4. Zentraler Ablauf: Zeit nachtragen

1. Die angemeldete Person öffnet die Webseite und sieht sofort das Erfassungsformular sowie ihre letzten Einträge.
2. Das Datum ist mit heute vorbelegt; die Person wird automatisch aus dem angemeldeten Konto übernommen.
3. Die Person trägt die Dauer ein und beschreibt kurz die Tätigkeit.
4. Ein Klick auf **Speichern** legt den Eintrag als Entwurf an, bestätigt den Erfolg und aktualisiert die Übersicht und Tagessumme. Der passende Stundensatz wird automatisch anhand von Person und Leistungsdatum zugeordnet.
5. Das Formular ist anschließend für den nächsten Eintrag bereit. Bei einem Fehler bleiben die Eingaben erhalten.

**Beispiel:** Heute · 30 Minuten · „VPN-Zugang eingerichtet“ · Speichern.

Es gibt nur drei Pflichtangaben: **Datum, Dauer und Tätigkeitsbeschreibung**. Davon ist das Datum bereits ausgefüllt. Für häufige Dauern stehen Schaltflächen wie „15 Min.“, „30 Min.“ und „1 Std.“ bereit; andere Dauern bleiben frei eingebbar. Eine Schnellauswahl setzt den Wert und speichert noch nichts.

Die Abrechenbarkeit ist standardmäßig aktiviert und kann bei Bedarf geändert werden. Stundensatz und Betrag werden nicht manuell durch Mitarbeitende eingegeben. Fehlt ein passender Stundensatz, bleibt die Zeiterfassung möglich; die Administration erhält einen Hinweis und muss den Satz vor der Freigabe ergänzen.

Die Dauer ist die tatsächlich geleistete Zeit. Der MVP verlangt keine Start- und Enduhrzeit und nimmt keine automatische Rundung vor. Pausen werden nicht als geleistete Zeit eingetragen. Eine Sitzung mit Unterbrechung kann beispielsweise durch ihre tatsächliche Gesamtdauer oder durch mehrere getrennte Tätigkeiten erfasst werden.

**Vorgeschlagenes Bedienziel:** Ein typischer Eintrag lässt sich nach der Anmeldung in höchstens 20 Sekunden anlegen. Das Ziel wird mit repräsentativen Tätigkeiten und Mitgliedern des tatsächlichen Teams überprüft.

## 5. Funktionale Anforderungen

| ID | Priorität | Anforderung |
| --- | --- | --- |
| F-01 | Muss | Jede Person nutzt ein lokales Konto der Webseite mit Benutzername und Passwort. Die Administration legt Konten an und kann Passwörter zurücksetzen. Es gibt keine öffentliche Selbstregistrierung oder Abhängigkeit von einem externen Anmeldedienst. Ein initiales Passwort muss beim ersten Login geändert werden. |
| F-02 | Muss | Mitarbeitende sehen nach der Anmeldung direkt die Zeiterfassung. Datum und Person sind vorbelegt; Dauer und Tätigkeit können ohne Öffnen weiterer Dialoge eingegeben werden. Der Kunde startet stattdessen in seiner Berichtsübersicht. |
| F-03 | Muss | Erfassung erfolgt minutengenau über ein eindeutig beschriftetes Minutenfeld mit Schnellauswahl. Zulässig sind positive ganze Minuten und eine nicht leere Tätigkeitsbeschreibung. Vergangene Tage können gewählt werden. |
| F-04 | Muss | Eigene Einträge sind nach Datum sichtbar; eigene Entwürfe können bearbeitet oder gelöscht werden. Vor dem Löschen ist eine Bestätigung nötig. Die Übersicht zeigt Tages- und Monatssummen sowie den Status. |
| F-05 | Muss | Die verantwortliche Person kann nach Zeitraum, Person, Status und Abrechenbarkeit filtern. Gesamtdauer, abrechenbare Dauer und Geldbeträge werden insgesamt und je Person berechnet. Entwürfe werden als vorläufig ausgewiesen. |
| F-06 | Muss | Die aktuell gefilterten Einträge lassen sich innerhalb der jeweiligen Leserechte als CSV exportieren. Enthalten sind Kennung, Datum, Person, Tätigkeit, Minuten und Status; bei vorhandenen Betragsrechten zusätzlich Abrechenbarkeit, Stundensatz, Betrag und Währung. Korrekturen sind als eigener Positionstyp mit Originalreferenz erkennbar. Der Export muss sich in einer deutschsprachigen Excel-Installation korrekt öffnen lassen. |
| F-07 | Muss | Speichern zeigt einen eindeutigen Erfolgs- oder Fehlerzustand. Ein Doppelklick oder die Wiederholung derselben Speicheranfrage darf keinen zweiten Eintrag erzeugen. |
| F-08 | Muss | Die Administration kann Konten deaktivieren. Historische Einträge bleiben in Berichten erhalten. |
| F-09 | Muss | Erstellung und letzte Änderung werden mit Zeitpunkt und handelnder Person gespeichert. Korrekturen durch die Administration benötigen einen Grund. |
| F-10 | Soll | Wiederkehrende Tätigkeiten können aus eigenen letzten Einträgen übernommen werden. Vor dem Speichern lassen sich Datum, Dauer und Beschreibung prüfen und ändern. |
| F-11 | Soll | Eine optionale Kategorie, beispielsweise Support, Wartung oder Einrichtung, erleichtert die Auswertung. Sie ist keine Voraussetzung für einen Eintrag. |
| F-12 | Muss | Einträge können als abrechenbar oder nicht abrechenbar markiert werden. Beide Zeitsummen sind getrennt sichtbar; die tatsächlich erfasste Dauer bleibt erhalten. Nicht abrechenbare Einträge haben einen Betrag von null Euro. |
| F-13 | Muss | Die verantwortliche Person kann ausgewählte Einträge einzeln oder gemeinsam prüfen, für den Kunden freigeben und später als abgerechnet markieren. Freigegebene Einträge sind gegen normale Bearbeitung gesperrt. Abgerechnete Einträge sind unveränderbar; Korrekturen werden separat dokumentiert. |
| F-14 | Muss | Eine Änderungshistorie hält bei Bearbeitungen, Löschungen, Satzänderungen und Statuswechseln fest, wer wann welche Werte verändert hat. Administrative Gründe werden gespeichert. Zugriff auf die interne Historie erhält die verantwortliche Person. |
| F-15 | Kann | Eine Stoppuhr ergänzt die manuelle Eingabe. Pro Person läuft höchstens eine Stoppuhr; sie übersteht Seitenwechsel und Neuladen. Beim Beenden werden Beschreibung und Dauer vor dem Speichern geprüft. |
| F-16 | Muss | Ein eigener lesender Zugang stellt der externen Firma freigegebene und abgerechnete Leistungen mit Zeiten, Stundensätzen und Beträgen bereit. Der Kunde kann nach Zeitraum und Person filtern sowie Berichte exportieren. |
| F-17 | Kann | Ein formatierter PDF-Leistungsnachweis ergänzt den CSV-Export. |
| F-18 | Muss | Die Administration hinterlegt Stundensätze mit Gültigkeitszeitraum je Person. Ein gemeinsamer Standardsatz kann als Vorgabe dienen. Überlappende Gültigkeiten innerhalb derselben Zuordnung sind nicht zulässig. |
| F-19 | Muss | Der Betrag wird automatisch aus Dauer und zugeordnetem Stundensatz berechnet. Der verwendete Satz wird am Eintrag festgehalten; spätere Änderungen an den Stammdaten verändern bestehende Einträge nicht automatisch. |
| F-20 | Muss | Freigaben setzen vollständige Abrechnungsdaten voraus. Fehlende Sätze dürfen nicht stillschweigend als null Euro behandelt werden. Ein Freigabedialog zeigt Anzahl, Zeitraum, abrechenbare Dauer und Gesamtbetrag der Auswahl zur Prüfung. |
| F-21 | Muss | Alle internen Teammitglieder können die Tätigkeiten, Zeiten und Status der anderen lesen und nach Person oder Zeitraum filtern. Dies umfasst intern sichtbare Entwürfe, aber keine automatisch erteilten Rechte zur Betragsansicht oder Bearbeitung fremder Einträge. |
| F-22 | Muss | Die Administration kann lokale Konten, Rollen und Rechte verwalten. Rechteänderungen werden protokolliert und spätestens bei der nächsten Serveranfrage wirksam. Kundenkonten können keine internen Schreib- oder Verwaltungsrechte erhalten. |

Eine vollständige Rechnungserstellung gehört zunächst nicht zum Umfang. „Abgerechnet“ wird manuell durch die verantwortliche Person gesetzt und bedeutet, dass die Leistung in der extern geführten Abrechnung berücksichtigt wurde; es ist kein Zahlungsstatus. Optional kann eine Rechnungsreferenz hinterlegt werden.

Die Oberfläche gliedert sich in **Zeit erfassen**, **Meine Zeiten**, **Teamübersicht**, **Abrechnung**, **Kundenübersicht** und **Verwaltung**. Alle internen Personen sehen die Teamübersicht; weitere Bereiche und Aktionen richten sich nach ihren Rechten.

## 6. Daten und fachliche Regeln

Ein Zeiteintrag enthält mindestens:

| Datenfeld | Bedeutung |
| --- | --- |
| Eindeutige Kennung | Identifiziert den Eintrag dauerhaft. |
| Person | Wird aus dem Konto übernommen; keine freie Auswahl beim normalen Erfassen. |
| Leistungsdatum | Tag, an dem die Tätigkeit ausgeführt wurde; unabhängig vom Zeitpunkt der Eingabe. |
| Dauer in Minuten | Positive ganze Zahl als Grundlage aller Summen. |
| Tätigkeitsbeschreibung | Kurzer, verständlicher Leistungsnachweis; vorgeschlagenes Limit: 500 Zeichen. |
| Abrechenbarkeit | Ja oder nein; vorgeschlagene Vorgabe ist ja. |
| Verwendeter Stundensatz | Satz und Währung, passend zum Leistungsdatum, als festgehaltener Wert am Eintrag. Bei Entwürfen darf der Satz noch fehlen. |
| Betrag | Aus Dauer und Satz berechneter Geldbetrag; bei fehlendem Satz ausdrücklich „noch nicht berechnet“. |
| Status | Entwurf, freigegeben oder abgerechnet, einschließlich der jeweiligen Zeitpunkte und handelnden Personen. |
| Erstellungs- und Änderungsdaten | Zeitpunkt und handelnde Person; Änderungsgrund bei administrativer Korrektur; Verweis auf die Historie. |
| Optionale Felder | Kategorie und Rechnungsreferenz. |

Weitere benötigte Datenobjekte sind lokales Benutzerkonto, Rolle, Berechtigung und deren Zuordnungen, die externe Firma, Stundensatz mit Gültigkeitszeitraum und Änderungsereignis. Eine spätere Abrechnungskorrektur besitzt eine eigene Kennung und einen Bezug zum unveränderten Originaleintrag.

Vorgeschlagene Regeln für den MVP:

- Zukünftige Leistungsdaten werden abgelehnt; geplante Arbeiten sind keine bereits geleisteten Zeiten.
- Ein Eintrag darf höchstens 1.440 Minuten umfassen. Ungewöhnlich hohe Tagessummen lösen zusätzlich eine Warnung aus; der genaue Schwellenwert ist abzustimmen.
- Mehrere Personen dürfen am gleichen Tag an derselben Aufgabe arbeiten. Ihre Zeiten zählen jeweils zum Gesamtaufwand.
- Ähnliche Beschreibungen oder gleiche Dauern sind allein kein Grund, Einträge abzulehnen. Schutz vor doppeltem Speichern betrifft dieselbe Speicheraktion.
- Summen werden aus ganzen Minuten berechnet und zum Beispiel als „2 Std. 15 Min.“ angezeigt. „1:30“ darf nicht als 1,30 Dezimalstunden interpretiert werden.
- Ohne Start- und Enduhrzeiten ist eine zeitliche Überschneidungsprüfung nicht möglich und nicht Bestandteil des MVP.
- Beschreibungen sollen die Arbeit erklären; Passwörter, Zugangsdaten oder unnötige personenbezogene Angaben gehören nicht hinein.
- Für den MVP wird eine Firma fest zugeordnet. Zusätzliche Firmen würden eine Erweiterung der Zuordnung, Filter und Zugriffsrechte erfordern.

**Betragsberechnung:** Für abrechenbare Einträge gilt `Betrag = Minuten / 60 × Stundensatz`. Vorgeschlagen wird minutengenaue Berechnung ohne Mindestdauer oder Aufrundung auf Zeitblöcke. Geldbeträge werden je Eintrag kaufmännisch auf zwei Nachkommastellen gerundet; Berichtssummen addieren diese gerundeten Einzelbeträge. Beispielwerte, keine Preisvorgabe: 45 Minuten bei 80,00 Euro pro Stunde ergeben 60,00 Euro netto. Drei Einträge zu je einer Minute bei 100,00 Euro pro Stunde ergeben jeweils 1,67 Euro und zusammen 5,01 Euro.

Für Geldwerte ist eine exakte Dezimal- oder Centberechnung erforderlich. Ein nicht abrechenbarer Eintrag trägt null Euro zur Summe bei, seine Dauer bleibt als Aufwand sichtbar. Solche Einträge dürfen auch ohne Stundensatz freigegeben werden.

Bei rückwirkenden Einträgen gilt der zum Leistungsdatum gültige Satz. Ändert sich bei einem Entwurf das Leistungsdatum oder die Person, wird die Satzzuordnung neu geprüft. Eine neue Satzversion ändert bereits gespeicherte Einträge nicht automatisch. Eine ausdrücklich ausgelöste Neuberechnung ist nur für Entwürfe und mit Protokollierung zulässig.

**Freigabeablauf:** Entwurf → freigegeben → abgerechnet. Der Kunde sieht freigegebene und abgerechnete Einträge; Entwürfe bleiben intern. Die Administration kann einen noch nicht abgerechneten Eintrag mit Grund in den Entwurfsstatus zurücksetzen. Er verschwindet dann bis zur erneuten Freigabe aus dem aktuellen Kundenbericht. Bereits heruntergeladene Exporte bleiben unverändert; deshalb enthalten Exporte Eintragskennungen und den Erstellungszeitpunkt des Berichts.

Abgerechnete Originaleinträge werden weder verändert noch gelöscht. Erforderliche Berichtigungen erfolgen als separat ausgewiesene, begründete Korrekturposition mit Bezug zum Original und dessen Leistungsdatum. Sie enthält getrennte Zeit- und Betragsdifferenzen, damit sich beide Summen nachvollziehbar berichtigen lassen. Eine reine finanzielle Korrektur verändert keine Arbeitsminuten. Beispiel: Wurden 60 statt 45 Minuten zu 80,00 Euro erfasst, korrigiert eine Position mit −15 Minuten und −20,00 Euro das Ergebnis auf 45 Minuten und 60,00 Euro. Negative Werte sind ausschließlich in diesem administrativen Korrekturablauf zulässig. Korrekturpositionen durchlaufen ebenfalls die Freigabe und werden in Summen und Exporten separat berücksichtigt. Der Kunde sieht zusätzlich den Veröffentlichungszeitpunkt der Korrektur; interne Änderungsgründe bleiben verborgen, stattdessen gibt es eine kurze kundenlesbare Erläuterung.

## 7. Anforderungen an Bedienung, Sicherheit und Betrieb

| ID | Bereich | Anforderung und vorgeschlagenes Prüfziel |
| --- | --- | --- |
| N-01 | Mobile Nutzung | Erfassung funktioniert ab 360 Pixel Ansichtsbreite ohne horizontales Scrollen. Schaltflächen sind ausreichend groß für Touchbedienung. |
| N-02 | Verständlichkeit | Deutsche Oberfläche, eindeutige Beschriftungen und konkrete Fehlermeldungen direkt am betroffenen Feld. Fehler werden nicht ausschließlich durch Farbe angezeigt. |
| N-03 | Zugänglichkeit | Der gesamte Erfassungsablauf ist per Tastatur bedienbar; Eingabefelder besitzen zugängliche Beschriftungen und der Fokus ist sichtbar. |
| N-04 | Geschwindigkeit | Bei bis zu zehn gleichzeitig aktiven Personen erscheinen Übersicht und Speicherbestätigung unter normalen Netzbedingungen in höchstens zwei Sekunden. Als vorläufiges Testvolumen dienen 50.000 Einträge. Umfangreiche Exporte dürfen länger dauern und zeigen einen Fortschrittszustand. |
| N-05 | Zugriffsschutz | HTTPS zwischen Browser und vorhandenem Proxy; geschützter interner Verbindungsweg zum Webserver. Prüfung der Berechtigungen bei jedem Datenzugriff auf dem Server. Geänderte Kennungen oder manipulierte Anfragen dürfen weder unberechtigte Änderungen noch die Einsicht in geschützte Geldwerte, Entwürfe durch Kunden oder Verwaltungsdaten ermöglichen. |
| N-06 | Anmeldung | Lokale Benutzername-Passwort-Anmeldung mit nativen PHP-Funktionen für Passwort-Hashing und Sitzungen. Zentrale PHP-Module prüfen Anmeldung, Rechte und CSRF-Schutz. Erforderlich sind Schutz gegen massenhafte Anmeldeversuche, administratives Zurücksetzen und Widerruf bestehender Sitzungen bei Deaktivierung oder Passwortreset. |
| N-07 | Ausfallsicherheit | Bei einem Verbindungsfehler wird kein erfolgreicher Speichervorgang vorgetäuscht. Eingaben bleiben während der geöffneten Seite erhalten und können erneut gesendet werden. Vollständige Offline-Nutzung gehört nicht zum MVP. |
| N-08 | Datensicherung | Vorgeschlagen sind tägliche automatisierte Sicherungen und eine vor dem Produktivstart geprüfte Wiederherstellung. Als vorläufiges Ziel gelten höchstens 24 Stunden Datenverlust und Wiederherstellung innerhalb eines Arbeitstags. |
| N-09 | Betrieb | Hosting, technische Zuständigkeit, Updates, Fehlerprotokolle und der Umgang mit Störungen sind vor dem Produktivstart festgelegt. Protokolle enthalten keine Passwörter oder Sitzungsgeheimnisse. |
| N-10 | Datenverwaltung | Zugriffsberechtigte Personen, Aufbewahrungsdauer, Löschung und Backup-Aufbewahrung werden abgestimmt. Beim CSV-Export werden nutzereingegebene Texte so behandelt, dass sie beim Öffnen keine Tabellenformeln ausführen. |
| N-11 | Reverse Proxy | HTTPS und Zertifikatserneuerung erfolgen am vorhandenen Proxy. Der interne Webserver ist ausschließlich über den vorgesehenen Proxyweg erreichbar. Die Anwendung berücksichtigt dessen vertrauenswürdige Angaben zu öffentlichem Host, HTTPS und Clientadresse; öffentliche Links, Redirects und sichere Sitzungscookies funktionieren auch bei internem HTTP. |
| N-12 | Technik und Portabilität | Die Anwendung verwendet PHP, natives JavaScript, MySQL und lokal enthaltenes Tailwind-CSS. Das Lieferpaket funktioniert mit drei Diensten für Nginx, PHP-FPM und MySQL ohne Frameworkinstallation, Composer, npm oder CSS-Build auf dem Zielserver. PHP-Erweiterungen sind im PHP-Image enthalten. Ein Umzug umfasst Dateipaket, Konfiguration und konsistenten Datenbankexport/-import. |

Leistungs- und Wiederherstellungswerte sind vorgeschlagene Anforderungen, keine bereits zugesicherten oder gemessenen Eigenschaften.

## 8. Abnahmekriterien für die erste Version

Die erste Version gilt als fachlich abnahmefähig, wenn die folgenden Abläufe funktionieren:

1. Eine durch den Admin angelegte Person meldet sich mit ihrem lokalen Konto an, ändert beim ersten Login das initiale Passwort, erfasst „30 Minuten – VPN-Zugang eingerichtet“ und sieht genau einen neuen Eintrag mit dem heutigen Datum und ihrem Namen.
2. Ein rückwirkender Eintrag lässt sich ohne Änderung von Einstellungen auf einen vergangenen Tag buchen. Ein zukünftiges Datum wird mit verständlicher Fehlermeldung abgelehnt.
3. Aus Einträgen von 30 und 45 Minuten entsteht die Summe „1 Std. 15 Min.“. Eine Änderung von 30 auf 20 Minuten aktualisiert sie auf „1 Std. 5 Min.“.
4. Leere Tätigkeit, null Minuten und negative Dauern werden abgelehnt, ohne andere Formulareingaben zu verlieren.
5. Ein Mitglied kann Tätigkeiten und Zeiten anderer Teammitglieder einschließlich Entwürfen lesen und filtern. Ohne Zusatzrecht kann es weder fremde Einträge ändern oder löschen noch geschützte Stundensätze und Beträge abrufen; dies gilt auch bei direkten Datenanfragen.
6. Ein Bericht für einen bestimmten Monat und eine bestimmte Person enthält genau die passenden Einträge. Der CSV-Export hat dieselbe Eintragszahl, Minutensumme und bei vorhandenen Betragsrechten Geldsumme wie die gefilterte Ansicht.
7. Doppelklick und Wiederholung derselben Speicheranfrage erzeugen jeweils nur einen Eintrag. Zwei bewusst neu erfasste, inhaltlich identische Tätigkeiten bleiben dagegen möglich.
8. Beim Abbruch der Verbindung bleibt die Eingabe auf der geöffneten Seite verfügbar. Eine erneute Übertragung führt zu einem eindeutig bestätigten Ergebnis ohne Duplikat.
9. Ein deaktiviertes Konto erhält auch über eine bestehende Sitzung keinen weiteren Zugriff; seine zuvor erfassten Zeiten bleiben auswertbar.
10. Der Erfassungsablauf funktioniert am Smartphone und per Tastatur. Mit kurzen Beispieltätigkeiten wird überprüft, ob das vorgeschlagene 20-Sekunden-Ziel erreicht wird.
11. Eine administrative Korrektur speichert die handelnde Person, den Zeitpunkt und den Änderungsgrund. Löschen erfordert eine Bestätigung.
12. Eine Sicherung wird in einer Testumgebung wiederhergestellt; Einträge, Zuordnungen und Summen stimmen mit dem gesicherten Stand überein.
13. Mit dem Beispielsatz 80,00 Euro ergeben 45 abrechenbare Minuten genau 60,00 Euro. Ein nicht abrechenbarer Eintrag derselben Dauer bleibt in der Aufwandsübersicht enthalten und hat null Euro Betrag.
14. Drei Einträge zu je einer Minute beim Beispielsatz 100,00 Euro ergeben je 1,67 Euro und als Berichtssumme 5,01 Euro. Oberfläche und Export stimmen überein.
15. Ein nachträglich erfasster Eintrag erhält den zum Leistungsdatum gültigen Satz. Eine spätere Satzänderung verändert seinen gespeicherten Satz und Betrag nicht automatisch.
16. Ein abrechenbarer Entwurf ohne passenden Satz kann gespeichert, aber nicht freigegeben werden. Er erscheint nicht als vermeintlich kostenlose Leistung im Kundenbericht.
17. Ein Kundenkonto sieht nur freigegebene und abgerechnete Einträge. Entwürfe, interne Änderungsgründe und Verwaltungsfunktionen sind auch über direkte Datenanfragen nicht zugänglich; Schreibversuche werden abgewiesen.
18. Mitarbeitende können freigegebene Einträge nicht mehr ändern. Abgerechnete Originaleinträge bleiben auch bei einer administrativen Korrektur unverändert; die Korrektur erscheint nach Freigabe separat im Kundenbericht und Export. Im Korrekturbeispiel aus Abschnitt 6 ergeben Original und Korrektur zusammen genau 45 Minuten und 60,00 Euro.
19. Ein administrativ zurückgezogener, noch nicht abgerechneter Eintrag verschwindet bis zur erneuten Freigabe aus dem aktuellen Kundenbericht. Rücknahme und erneute Freigabe sind intern nachvollziehbar.
20. Der Admin kann einer internen Person beispielsweise Betragsansicht und Freigaberechte erteilen und entziehen. Die Änderung gilt auch bei bereits angemeldeten Personen spätestens mit der nächsten Serveranfrage und wird protokolliert.
21. Öffentliche Selbstregistrierung und die Vergabe interner Verwaltungsrechte an Kundenkonten sind ausgeschlossen. Das Entfernen oder Deaktivieren des letzten aktiven vollständigen Admins wird abgewiesen.
22. Anmeldung und Zeiterfassung funktionieren über die öffentliche HTTPS-Adresse ohne Weiterleitungsschleife. Sitzungscookies sind als sicher markiert, Links enthalten die öffentliche HTTPS-Adresse, und manipulierte Forwarded-Header eines Clients werden nicht als vertrauenswürdig übernommen. Der interne Webserver ist von nicht zugelassenen Quellen aus nicht direkt erreichbar.
23. Das vollständige Paket wird auf einen zweiten vorbereiteten Docker-Host kopiert, die lokale Konfiguration angepasst und ein SQL-Export importiert. Anmeldung, Zeiterfassung und Berichte funktionieren ohne Installation eines Anwendungsframeworks oder Entwicklungswerkzeugs. CSS und JavaScript sind lokal verfügbar; benötigt werden nur die drei vorgesehenen Laufzeitdienste und der vorhandene Proxy.

## 9. Abgrenzung und offene Entscheidungen

**Zunächst außerhalb des Umfangs:** Lohnabrechnung, Urlaubs- und Anwesenheitsverwaltung, Schichtplanung, automatische Rechnungserstellung, native Smartphone-App, vollständiger Offline-Betrieb, Standorterfassung sowie eine umfassende Projekt- oder Ticketverwaltung.

Vor der Umsetzung sind insbesondere diese Entscheidungen zu treffen:

| Frage | Ausgangsvorschlag |
| --- | --- |
| Wie viele Personen arbeiten gleichzeitig damit? | Bis zu zehn als vorläufige Auslegungsgröße. |
| Sind die Verkaufssätze einheitlich oder je Person verschieden? | Sätze je Person mit Gültigkeitszeitraum; gemeinsamer Standard möglich. Konkrete Werte noch offen. |
| Gelten Mindestdauer oder feste Abrechnungsblöcke? | Tatsächliche Minuten ohne Zeitrundung abrechnen; nur Geldbeträge auf Cent runden. |
| Sind Euro und Netto-Ausweisung passend? | Euro und netto als Arbeitsannahme; keine automatische Steuer- oder Rechnungsberechnung. |
| Welche Details soll der Kunde sehen? | Freigegebene Tätigkeiten mit Datum, Anzeigename, Dauer, Satz, Betrag und Status; keine internen Kontodaten. |
| Wer prüft und veröffentlicht die Einträge? | Die verantwortliche Person; Freigabe einzeln oder gesammelt, zum Beispiel monatlich. |
| Wo wird die Anwendung betrieben und wer betreut sie? | Vor Umsetzung anhand der vorhandenen Infrastruktur festlegen. |
| Wie lange bleiben Einträge, Änderungsdaten und Sicherungen erhalten? | Mit der verantwortlichen Person und der externen Firma abstimmen. |

**Empfohlener erster Ausbau:** Ein mobil gut bedienbares Erfassungsformular, eigene Einträge und gemeinsame Teamübersicht, lokale Konten mit administrativer Rechtevergabe, zentral gepflegte Stundensätze mit automatischer Betragsberechnung, eine Abrechnungsübersicht, Freigabe mit Änderungshistorie, ein Kundenbereich mit Leserechten sowie CSV-Export. Stoppuhr, Tätigkeitsvorlagen und PDF-Berichte sind spätere Erweiterungen.
