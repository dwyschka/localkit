# Änderungen: `beta` vs. `main`

Vergleich: `origin/main` (884bff0) ↔ `origin/beta` (082d6c9)
Es existiert kein `master`-Branch in diesem Repo – `main` ist der Standard-Branch (`origin/HEAD -> origin/main`), daher wurde dieser als Referenz verwendet.

**Umfang:** 205 Commits auf `beta`, die nicht in `main` sind (nur 1 Merge-Commit in `main`, der nicht in `beta` ist). 252 geänderte Dateien, +15.681 / −3.970 Zeilen.

---

## 1. Framework-Upgrade (Laravel & Filament)

- **Laravel 12 → 13** Upgrade, inkl. Filament-Patch auf 3.3.54 (`7453aab`)
- **Filament 5** Upgrade (`2c58643`), inkl. neu veröffentlichter Inter-Webfonts (`057299e`)
- Massive Anpassungen an `composer.json`/`composer.lock` (+2.689 Zeilen im Lockfile), `package.json`/`package-lock.json`
- Entfernte veraltete Filament-v3-Assets: ungenutztes Theme, verwaiste Plugin-Assets, `filament-laravel-log.js` (−960 Zeilen) (`8f10752`)
- Zahlreiche Kompatibilitätsfixes durch das v3→v5-Upgrade:
  - `PetResource`-FileUpload nutzte nicht mehr existierende Filament-v5-Methodennamen (`0601bfa`)
  - Warning-Alert-Icon durch Upgrade kaputt (`768b3b6`)
  - `heroicon-m-eraser` existiert nicht mehr, ersetzt durch `heroicon-m-backspace` (`dfa60d7`)

## 2. Admin-UI / Navigation

- Umstellung der Panel-Navigation von Sidebar auf **Top-Bar** für mehr Platz (`61633e0`), zentrierte Navigation, Breadcrumbs entfernt, Nav-Reihenfolge korrigiert (`38ce997`)
- Geräteliste zeigt jetzt **alle Geräte ohne Pagination/Suche** (`c5bb49e`)
- Neue **Card-basierte Devices & Pets UI** mit Pro-Pet-Bilder-Upload (`bfbd16b`)
- Bluetooth-Geräte: Kachel-Layout (Tile Layout), Live-Stream-Action, Seriennummer ins Formular verschoben (`7cab235`), später auf Single-Column-Fieldset korrigiert (`32cf205`)
- Geräte-Formular: gemeinsame Felder in „Device“- und „OTA“-Sections gruppiert (`5e618aa`), volle Fieldset-Breite erzwungen (`043e789`, `63dc7d1`, `1c80692`)
- OTA-Icon neben `working_state`-Badge verschoben (`3178874`)
- Kamera-Snapshot-/Stream-Actions entfernt zugunsten Live-Stream-Kachel (`c15f4fc`)
- Neue **Logs-Seite** (`app/Filament/Pages/LogsPage.php`) unter `/logs` mit Download-, Clear- und Delete-Actions für Logdateien (`490b2b0`, `60056f1`, `284d40f`)
- Neue **Media-Seite** (`app/Filament/Pages/MediaPage.php`) zum Durchsuchen des Object-Storage-Disks, inkl. Datei-Löschen und „Alles löschen“ (`b28d76a`, `51035d2`, `48d58be`)
- Dashboard-Widgets: „Recent Activity“-Feed und „Pet Event Counts pro Tag“ (`45521c9`), Standard-Widgets (Account/Filament Info) ausgeblendet (`04e6f9e`), Pet-Activity-by-Day vor Recent-Activity-Feed sortiert (`d5ad763`), auf letzte 3 Tage begrenzt (`a025726`)
- Pet-Foto-Upload: quadratischer Crop erzwungen, max. 360×360 (`a34dc6f`, `415106a`), Save-Button im Bild-Editor auf Mobile-Hochformat gefixt (`affb57e`)
- Diverse TimePicker-/Schedule-Formular-Bugs behoben (siehe Abschnitt 7)

## 3. Neue Geräteunterstützung / Geräte-Refactoring

- **Reorganisation der Geräte-Klassen** in einheitliche Ordnerstruktur `app/Petkit/Devices/<Name>/{Device,Configuration,UI}.php` (vorher z.B. `PetkitPuraMax.php` als Einzeldatei) für: `PuraMax`, `FreshElement3`, `FreshElementSolo`, `PurobotCrystal`, `YumshareDual`, `YumshareSolo` (`2b39df3` u.a.)
- **Neues Gerät: EversweetUltra** – komplette Implementierung mit `Configuration.php` (1.719 Zeilen), `Device.php` (639 Zeilen), `UI.php` (317 Zeilen) (`1eddc95`)
  - Wasserstand-Alert-Switches zu Binary Sensors konvertiert, überflüssige Entities entfernt (`d94bef8`)
  - Weitere Entity-Trimmung, Cloud Recording entfernt, „Reset Cube“-Button ergänzt (`2fbca48`)
  - „Reset Water“ umbenannt (`a410b5d`)
- **Purobot Crystal (T7)**: Aktivitäts-Tracking aus `purobot_crystal.log` implementiert (`e4df019`), `dev_discern_config` implementiert (`9665aea`)
- **W7H** (neues Bluetooth-Gerät): Konfiguration anhand APK-Analyse korrigiert, Cube-Consumable und Pump-Actions ergänzt (`35ea6bc`), Fehler-/Diagnosezustände in HA freigegeben, verschachteltes Error-Parsing gefixt, Flush/Deep-Clean als funktionierend markiert (`f8d80ff`), Fehler-/Work-State-Handling für `property_post`/Event-Topics gefixt (`0b21b6a`), Ableitung von `working_state`/Error vereinheitlicht (`73d7982`), Abonnement der Topics `pet_discern`/`drink_start`/`drink_over` (`fbece3f`)
- **W5 Wasserspender** (Bluetooth): umfangreiche neue Funktionalität
  - BLE-Write-Actions für Mode/Power/Filter-Reset (`398ebfb`)
  - Fix „Malformed UTF-8“-Queue-Fehler durch fehlendes Base64-Encoding vor Dispatch (`158f2fe`)
  - Fountain-Modus (Normal/Smart) in HA/Admin-UI (`e6e510c`)
  - Alle verbleibenden Werte an HA/Admin-UI exponiert (`5634e90`)
  - Fix: `filterTimeLeftDays` war um Faktor 100 überhöht (`c7d7b90`)
  - Ablehnung fehlerhafter Status-Frames statt stiller Korruption, „Last Update“ ergänzt (`044fc9e`)
  - Power/Mode-Steuerung über Detail-Formular statt separate Buttons (`03dbca3`)
  - Fix: langsame Saves durch verschachtelte, redundante MQTT-Roundtrips (`a2b6cbf`)
  - Power/Mode/Reset-Filter als beschreibbare HA-Entities (`e631bb9`)
  - „Refresh Data“ als HA-Button (`8fe4174`)
- **Proxy-Modus entfernt** – durch bessere Update-Experience ersetzt, alle Geräte werden jetzt direkt genutzt (`481d506`, `685f10e`, `652f546`, `f1508aa`)
- OTA-Logik in separate Controller aufgeteilt, Logging erweitert (`8a7f428`)
- Telnet-basierter Reboot für NextGen-Geräte (`ea72083`), neuer `app/Petkit/TelnetClient.php`
- `isNextGen()` null-safe gemacht, Crash in Camera-Stream-Tile-Spalte behoben (`41006c9`, `c611fda`)
- HA-Entities aufgeräumt: Feed-Button entfernt zugunsten `mqtt.publish` (`b6e9c0e`, dann revertiert `6530528`), Tür-Binary-Sensor für YumShare-Feeder entfernt (`f34ab96`), „pet/move/eat/drink detected“-Binary-Sensors entfernt zugunsten HA-Events (`037d0dc`), diverse EversweetUltra/YumshareDual-Entity-Trimmungen
- „Last Used By“-Status geräteübergreifend, publiziert aufgelösten Pet-Namen an HA (`b78ceff`)
- Bowl-Sensor-Wert read-only in YumshareDual-Admin-UI angezeigt (`a454192`)
- Geräte-Actions werden ausgeblendet, wenn nicht per MQTT verbunden (`150696a`)

## 4. Home Assistant Integration

- **Vollständige HA-MQTT-Entity-Plattform-Bibliothek implementiert** (`108cc60`) – neue Klassen für praktisch alle HA-Entity-Typen: `AlarmControlPanel`, `Camera`, `Climate`, `Cover`, `Date`, `DateTime`, `DeviceTracker`, `DeviceTrigger`, `Event`, `Fan`, `Humidifier`, `Infrared`, `LawnMower`, `Light`, `Lock`, `Notify`, `Scene`, `Siren`, `Tag`, `Text`, `Time`, `Update`, `Vacuum`, `Valve`, `WaterHeater` (jeweils neue Datei unter `app/Homeassistant/`)
- History-Einträge werden als HA **Event-Entities** publiziert (`70f8716`), später auf einen eigenständigen `EventPublisher` umgestellt statt History-Model-basiertem Publishing (`f46d8ea`), `EventPublisher` feuert für jede in Logdateien bestätigte Aktivität (`6306ad7`)
- Vollständige HA-Entity-Abdeckung über alle Geräte-Configs hinweg (`e60d503`)
- Fehlende HA-Entities für PurobotCrystal ergänzt (`4a6af0e`)
- `MergesExtraPayload`-Concern für HA-Entities (`app/Homeassistant/Concerns/MergesExtraPayload.php`)

## 5. Media, Object Storage & Kamera

- **S3/Object-Storage-Anbindung**: neue `config/filesystems.php`-Einstellungen, neue Doku `s3.md` (95 Zeilen, „S3/Object Storage Setup Guide“, `e32a32c`)
- `ObjectStorageController` für Upload-Handling (`app/Http/Controllers/Petkit/ObjectStorageController.php`)
- `DevUploadFileInfoV2Controller` massiv erweitert (+289 Zeilen) – zentrale Logik für Datei-Uploads von Geräten
- AES-Schlüssel-Objekte in festem Bucket-Ordner `aeskeys/` gespeichert (`ae47735`), Pfad-Fix auf `AESKEY/{serial_number}.txt` (`594112d`)
- **Medien-Entschlüsselung**: hochgeladene Medien werden in-place entschlüsselt und über `/media/file/{fileId}` als Klartext ausgeliefert (`153ab74`, neue Klasse `app/Petkit/Storage/MediaDecryptor.php`), zunächst deaktiviert wegen Objekt-Key-Korrelationsproblemen (`13ffa24`), nach Klärung per Reverse-Engineering wieder aktiviert (`00a6e6a`)
- **Video-Remuxing**: `.ts`-Aufnahmen werden zu fragmentiertem MP4 für Browser-Wiedergabe remuxt (`3b57b9c`/`3b59670`, neue Klasse `app/Petkit/Storage/VideoRemuxer.php`), `aac_adtstoasc`-Bitstream-Filter ergänzt (`95296b8`), PTS/DTS-Regenerierung beim Remux (Fix für Segment-Resets in kombinierten Videos) (`ca7e953`), Wechsel auf ffmpeg **concat-Demuxer** statt Roh-Byte-Konkatenation (`9fef009`), Segmente werden vor dem Remux zusammengeführt (`6e8fd93`)
- Kamera-Thumbnails: neuer `CameraThumbnailController`, generiert via ffmpeg aus go2rtc's `frame.mp4` (`2b5dbb5`), Abruf über Laravel-HTTP-Client und Pipe in ffmpeg-stdin (`b2abfd4`), zunächst Rendering-Block durch synchrone go2rtc-Stream-Discovery entfernt (`0c4fa39`), dann wieder auf synchrone Discovery zurückgesetzt (`b336cb4`)
- Live-Kamera-Stream im Edit-View-Media-Bereich (`ba16b54`)
- Kamera-Clips werden über eine gemeinsame `event_id` mit Activities verknüpft, Eat/Detect-Events getrackt (`458b70f`), `.ts` wird nach Remux gelöscht, `.mp4` als kanonisch verlinkt, Activities paginiert (`fc2f71e`)
- Log-Download exzessiver Speicherverbrauch bei großen Dateien behoben (`fe419ec`? – korrigiert: `6826169`)
- Jeder eingehende Request wird nach `storage/logs/http.log` geloggt (`2891c4b`), später wieder entfernt (`c07b588`), sowie Debug-Logging für Objekt-Storage-PUTs mit Objekt-Key (`fdfdc46`)

## 6. Aktivitäts-Log / Pet Discern (Tiererkennung)

- Neue Tabelle/Migration **`pet_images`** – Pet-Fotos als echte Relation statt einzelnem Feld (`6947b3d`), Discern-IDs nutzen jetzt die Media-ID
- **`dev_discern_pic`-Endpoint** für Tiererkennungsbilder (`c68537b`), mit echten Pet-Daten befüllt, konfigurierbare Pet-Farbe (`558ba54`)
  - Diverse Korrekturen: reale Pet-ID statt `id*1000+index` (`8d82864`), eindeutige 6-stellige Bild-ID (`9b5f7a6`), Farbfeld großgeschrieben (`6e259e7`), erzwungenes `http` (nie `https`) für Bild-URLs (`2a4c760`)
- Discern-Fotos werden beim Ausliefern auf 224×224 skaliert, falls nötig (`6f801a5`)
- `thing.service.discern` wird bei jedem neuen Pet-Foto publiziert (`99cf06f`)
- `History.pet_id` wird jetzt aus dem asynchronen `pet_discern`-Event gesetzt, nicht nur aus Parametern (`a72940c`)
- Temporärer `FAKE_DISCERN_REQ`-Schalter für Tests mit aufgezeichneter Antwort (`e1ee0db`), später entfernt (`95d6100`)
- **Activity-Log-Seiten**: eigene Activities-Seite je Pet (ersetzt RelationManager) (`c0151a2`), eigene Seite je Gerät (`c812af4` u.a.), Event-Detailseite mit vollständiger Dateiliste (`fa12af1`), Styling-Fixes (Timeline zu eng, Tages-Sektionen) (`832015d`, `0c3f8df`)
- Erkannter Pet-Name wird für alle History-Event-Typen angezeigt, nicht nur für einige (`bbbb0a5`)
- Aufnahmen ohne passende Activity als Fallback-Sektion angezeigt (`9d4f5a2`), später wieder entfernt zugunsten Filament-Pagination (`e02b56b`)
- EVENT_PREVIEW vor CLOUD_STORAGE in Medien-Listen sortiert (`c8e90c6`)
- Fix/Recompute-Button für Dauer-Neuberechnung & Re-Remux (`7fb888c`), dann auf Re-Encode statt Stream-Copy umgestellt (`e303966`), schließlich als Standard-Merge-Schritt übernommen, manueller Button entfernt (`e998ef1`)
- Artisan-Befehl zum Löschen verwaister Aufnahmen (`eb156a4`, `app/Console/Commands/DeleteUnlinkedMedia.php`)
- Scheduled Cleanup für Activity-Log und alte Mediendateien (`f2f2b4f`, `app/Console/Commands/CleanupActivityLog.php`)
- Deduplizierung von `ActivityLog`-Einträgen anhand `event_id` statt reinem Insert (`d698472`), Migration zur Bereinigung bestehender Duplikate (`9666129`)
- Activity-Detailseite hinter Debug-Modus versteckt, Listing-Medien reduziert (`38b78dd`)
- Backfill-Command für History-Pet-Zuordnung (`app/Console/Commands/BackfillHistoryPets.php`)
- EAT-History-Logging und HA-Events bei YumshareSolo `eat_start`/`eat_over` (`9bc4297`)

## 7. Fütterungspläne (Schedules) – umfangreiche Fehlersuche/-behebung

Dies war offensichtlich ein längerer Debugging-Prozess (viele aufeinanderfolgende Commits, teils Reverts):

- Neue Tabellen **`device_schedules`** und **`device_schedule_items`** (`2b6e...`, `a025...`), Migration der bisherigen Schedule-JSON-Daten in diese Tabellen (`create_device_schedules_table`, `create_device_schedule_items_table`, `migrate_schedule_json_to_device_schedule_tables`)
- Neue Models `DeviceSchedule.php`, `DeviceScheduleItem.php`
- D4SH: Fütterungsmenge aufgeteilt in Pro-Hopper-Werte `a1`/`a2` (`9bab481`)
- Off-by-one-Fehler bei Schedule-Item `t` vs. `id` behoben (`6592f3c`)
- Settings- und Schedule-Diffs zu einem einzigen `property_set` zusammengeführt (`b2689de`)
- Gerät wird vor MQTT-State-Callbacks aus der DB neu geladen (`2973aa9`)
- Wire-Format an den tatsächlichen Firmware-Parser angepasst, dokumentiert in `schedule.md` (`9c057d7`)
- `sche_enable` mehrfach entfernt/wieder eingeführt für D4SH – zunächst als „undokumentiert, unnötig“ entfernt (`42aacac`), dann wieder eingeführt, da geplante Fütterungen sonst nicht auslösten (`17c7b4b`), erneut entfernt (`ab817bf`), Revert davon (`1f6caba`), dann final wieder angewendet (`cc2a52c`)
- Wire-Serialisierung nach `schedule.md`-Revision gehärtet (`643a524`)
- `propertyChange()`-Fehler werden geloggt statt still verschluckt (`21bf98f`)
- Jedes D4SH-Event-Topic wird jetzt bestätigt (ack), nicht nur `ble_response` (`0e3a472`)
- Experimenteller `+1`-Offset auf Schedule-Item `t` (`bc352b1`), wieder zurückgenommen, da er das Problem nicht behob (`4e6f782`)
- `nextTick` im Wire-Payload um 1 erhöht (`61f2170`), dann korrigiert, damit er den nächsten statt den am weitesten entfernten Fütterungszeitpunkt widerspiegelt (`f0d2b9a`)
- Fütterungsmengen werden mit `factor1`/`factor2` skaliert, bevor sie gesendet werden (`2060481`)
- `MultiRangeDTO::$name`-Uninitialisiert-Fehler beim Speichern von YumshareSolo (`e5eb53a`) und FreshElementSolo/D4 (`2ccf797`) behoben
- „Share Open“- und „Multi Config“-Toggles entfernt (`3fcfd11`)
- Faktor read-only neben Fütterungsmenge angezeigt (YumshareSolo `ce6755b`, D4 `4659b07`)
- D4-Schedule speicherte nicht – Hidden-Fields für `id`/`t` eingeführt (`9e527d0`)
- D4SH-Schedule-Fixes auf D4/FreshElementSolo portiert (`a1`/`a2` → `a`) (`ba923af`) und auf YumshareSolo/D4H portiert (`db928b5`)
- „Reset Config“-Button zum Zurücksetzen eines Geräts auf Default-Konfiguration (`e885c65`)
- „Unknown“-Section aus FreshElementSolo/D4-Formular entfernt (`aab8400`)
- Kommas in `re` auf dem Wire beibehalten statt zu reinen Ziffern gestrippt (`1bfd108`)
- Kürzung/Rückgängigmachung der Schedule-Item-IDs für Null-Terminator-Handling (`shorten_schedule_item_ids_for_null_terminator` und zugehöriger Revert `revert_schedule_item_id_shortening`)
- Kleinere UI-Bugs: TypeError bei `formatStateUsing` für `waterChangeTime`/`flushTime` (`33843bf`), undefined array key `time_display` in FreshElementSolo- und weiteren Feeder-Schedules (`d63d06f`, `2ce5576`), „Days of Week“ setzte sich bei jedem Formular-Laden zurück (`1fd3ff9`), fehlende Option „on“ bei Surplus Food Control (`7ed3338`)
- `AnswerDTO` refaktoriert, um wie `DeviceConfigurationDTO` von `ValidatedDTO` zu erben (`105da07`), kurz darauf revertiert (`e80cb07`)
- Fix für stillen Konfigurationsverlust bei 5 UI-gebundenen Settings ohne DTO-Property (`f7a1995`)

## 8. Petkit-API / Routing / Sonstige Backend-Fixes

- Petkit-Routen konnten bei unbekanntem Pfad auf eine HTML-Fehlerseite durchfallen – jetzt korrekt behandelt (`53b3087`)
- Device-API-Routen liefern bei Fehlern JSON statt HTML (`436a005`)
- `toFeed`/`toFeedGet` gegen leeren Fütterungsplan abgesichert (`d1f0389`)
- `SuccessResource` crashte bei MQTT-Nachrichten ohne `id`/`method` (`6911290`)
- Fehlende STS-Credential-Felder in `dev_oss_sts_info_new_v2` ergänzt (`fe419ec`)
- Alle OCI-Routenparameter explizit gebunden, nicht nur `object` (`577886d`)
- `{history}`-Routenparameter zu `{historyId}` umbenannt, behebt 404 (`ca7ff54`)
- Payload-`event_start`/`event_end` für EAT/DRINK-Dauer statt DB-Timestamps genutzt (`ff1fcc4`)
- `@svg()`-Aufrufe mit unbekanntem `$style`-Parameter behoben (`b3d495a`)
- HomeAssistant-Integration-Fix (`fbcc365`)

## 9. Infrastruktur / Sonstiges

- `Dockerfile` und neue `docker-compose.yml` (35 Zeilen)
- `.env.example` um 21 Zeilen erweitert, `.gitignore` angepasst
- Neue zentrale `config/localkit.php` (81 Zeilen), `config/go2rtc.php`, Erweiterungen an `config/petkit.php`
- `.DS_Store` aus dem Repo entfernt (`79d4e66`), lokale Firmware/APK-Analyse-Notizen (`IMPLEMENT/`) nicht mehr getrackt (`1f4f214`)
- `resources/css/filament/petkit/tailwind.config.js` und `theme.css` entfernt (nicht mehr benötigt nach Filament-5-Upgrade)
- `lang/en/petkit.php` erweitert
- Test-Datei `tests/Http/D4SH/DevDiscernPic.http` hinzugefügt

---

## Zusammenfassung

`beta` ist gegenüber `main` **deutlich weiter fortgeschritten** und enthält:
1. Ein größeres Framework-Upgrade (Laravel 12→13, Filament 3→5)
2. Grundlegend überarbeitete Admin-UI (Top-Nav, Cards, neue Logs-/Media-Seiten, Dashboard-Widgets)
3. Komplett neue Home-Assistant-Entity-Plattform (24 neue Entity-Typen)
4. Ein neues unterstütztes Gerät (EversweetUltra) plus erweiterte Unterstützung für W7H und W5
5. Ein komplett neues Objekt-Storage-/Medien-Subsystem (S3, Entschlüsselung, Video-Remuxing, Kamera-Thumbnails)
6. Ein neues Pet-Discern-/Aktivitäts-Tracking-System mit eigenen Log-Seiten
7. Eine lange, iterative Fehlerbehebungs-Serie an der Fütterungsplan-Logik (Wire-Format, Schedule-Speicherung), die in der aktuellen `fix-schedules`-Branch-Arbeit mündet

`main` enthält im Vergleich nur einen zusätzlichen Merge-Commit (#34, der frühere `beta`-Stand von vor diesen 205 Commits).
