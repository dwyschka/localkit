# Purobot Max (T5) — ctrl_t5 MQTT interface

Analysis target: `t5/ctrl` (ELF 32-bit MIPS, stripped, firmware shipped with
the T5). Cross-checked against the decompiled companion app
`com.petkit.oversea 13.4.1` (`T5HomePresenter`, `T6SmartSettingActivity`,
`T6SettingActivity`, `T6BasicSettingsActivity`, `T6WorkingTimeActivity`,
`T6IntelligentDetectionActivity`, `T6SpeechSettingsActivity`,
`T6LightSettingPresenter`, `T6DoNotDisturbSettingPresenter`,
`T5RegularDeodorizationPresenter`, `AiPhActivity`, `AiVoiceActivity`,
`KidLockPresenter`). The app uses deviceType `21` for the T5; `27` is the T6.

## Topics

| Purpose | Topic |
|---|---|
| Settings push | `/sys/{productKey}/{deviceName}/thing/service/property/set` |
| Service command | `/sys/{productKey}/{deviceName}/thing/service/{start\|stop\|continue\|end}` |
| Service command with `type` selector | `/sys/{productKey}/{deviceName}/thing/service/{power\|reset_pet\|lapse\|...}` |
| State / events | `/sys/{productKey}/{deviceName}/thing/event/{event}/post` |
| Event acknowledgement | same topic + `_reply` |

`ctrl_t5`'s `parse_recv_from_topic()` routes `thing/service/property/set` into
four parsers:

- `parse_recv_property_set_normal()` — most user settings
- `parse_recv_property_set_algo_param()` — camera/AI detection settings
- `parse_recv_property_set_feed_param()` — `feed` / `feed_get`
- `parse_recv_property_cloud_service()` — cloud `capacity` token update

`thing/service/{method}` goes through `parse_service_invoke_msg()`.

## Values that **can** be set over MQTT

### `property/set` — normal parser

All values are sent inside the top-level `property` object. `ctrl_t5` accepts
either a raw JSON array/object or a stringified JSON value for the range and
schedule keys (it tries `cJSON_ParseWithLength()` when the value is a string).

| Key | Type | Meaning / notes |
|---|---|---|
| `autoWork` | 0/1 | Auto cleaning after the pet leaves |
| `autoIntervalMin` | int (seconds) | App values: 0, 300, 600, 900, 1800, 2700, 3600, 4500, 5400, 6300, 7200. Name says minutes, firmware uses seconds. |
| `fixedTimeClear` | 0/1 | Enable the cloud-managed cleaning schedule |
| `stillTime` | int (seconds) | Delay before cleaning. App: 0, 30, then 60…3600 in minute steps. |
| `avoidRepeat` | 0/1 | Skip repeated cleaning |
| `underweight` | 0/1 | Skip auto cleaning below `lightest` |
| `lightest` | int (g) | Minimum weight threshold |
| `kitten` | 0/1 | Kitten protection |
| `bury` | 0/1 | Cover waste before cleaning |
| `deepClean` | 0/1 | Extra cleaning pass |
| `tumbling` | 0/1 | Tumbling motor routine / “look inside” |
| `sandSaving` | 0/1 | Litter saving |
| `sandType` | int | 0 = not set, 1 = bentonite, 2 = tofu, 3 = mixed |
| `unit` | 0/1 | 0 = kg, 1 = lb |
| `disturbMode` | 0/1 | Cleaning do-not-disturb enable |
| `distrubMultiRange` | array of up to 10 `[from,till]` minute pairs | Cleaning DND period. Note firmware spelling `distrub`, not `disturb`. |
| `schedule` | array of up to 20 objects | `{id?, type, time, repeats}`; `type` 0 = clean, 1 = deodorize. The app edits this through PetKit's cloud HTTP API, but ctrl_t5 accepts the array directly. |
| `lightMode` | 0/1 | Screen/indicator light |
| `lightMultiRange` | array of up to 5 `[from,till]` pairs | Screen/toilet-light active period |
| `lightAssist` | 0/1 | Light assist during cleaning |
| `toiletLight` | 0/1 | Toilet light |
| `toneMode` | 0/1 | Voice-prompt do-not-disturb |
| `toneMultiRange` | array of up to 5 `[from,till]` pairs | Voice-prompt DND period |
| `systemSoundEnable` | 0/1 | System voice announcements |
| `soundEnable` | 0/1 | Sound enable |
| `volume` | int | App slider is 1…9 |
| `selectedSound` | int | Raw sound id; app gets the list from the server |
| `soundList` | array | Raw accepted sound list |
| `manualLock` | 0/1 | Child lock |
| `clickOkEnable` | 0/1 | OK button |
| `camera` | 0/1 | Camera switch |
| `microphone` | 0/1 | Camera microphone |
| `night` | 0/1 | Infrared night vision. The wire key is `night`; `infrared` is only a log/getter name. |
| `cameraLight` | 0/1 | Camera light |
| `timeDisplay` | 0/1 | Timestamp overlay. The wire key is `timeDisplay`; ctrl_t5 logs “set timestamp_enable” but does **not** read `timestamp_enable`. |
| `upload` | 0/1 | Cloud video upload |
| `language` | string | Locale, e.g. `en_US` |
| `autoSpray` | 0/1 | Auto deodorizing |
| `deepSpray` | 0/1 | Deep deodorizing (two consecutive passes) |
| `fixedTimeSpray` | 0/1 | Enable the cloud-managed deodorizing schedule |
| `sprayDays` | int | Raw spray counter |
| `downpos` | int | Cleaning-mechanism rest position; meaning of values not documented |
| `cameraMultiRange` | array of up to 7 objects | Camera working time: `{enable, rpt, time:[[from,till],...]}`. `rpt` is weekdays `1`…`7`; `enable` is accepted but **ignored** by ctrl_t5. |

### `property/set` — algorithm parser

| Key | Type | Meaning |
|---|---|---|
| `moveTracking` | 0/1 | Movement tracking |
| `moveSensitivity` | int | Movement sensitivity |
| `petDetection` | 0/1 | Pet appearance detection |
| `petTracking` | 0/1 | Pet tracking |
| `petSensitivity` | int | Pet sensitivity |
| `toiletDetection` | 0/1 | Toilet-use detection |
| `phDetection` | 0/1 | Urine pH detection |
| `voice` | 0/1 | AI voice messages |
| `detectInterval` | int | Detection interval |
| `detectMultiRange` | array of up to 5 `[from,till]` pairs | Detection period |

### `property/set` — feed parser

`feed` and `feed_get` are accepted; `feed` carries the schedule payload and
`feed_get` is the re-pull request path. These are not user-facing settings.

### `property/set` — cloud parser

`capacity` is parsed only to update the STS/token blob. It is not a user
setting.

### Service topics

`parse_service_invoke_msg()` reads the method from the topic and the action
code from the payload:

| Topic suffix | Payload key | Values |
|---|---|---|
| `power` | `power_action` | 0/1 |
| `start` | `start_action` | 0 clean, 1 dump, 2 deodorize/spray, 3 recover, 4 smooth, 6 calibrate, 7 light, 9 maintenance |
| `stop` | `stop_action` or `end_action` | same code as the running work mode |
| `continue` | `continue_action` | same code as the running work mode |
| `reset_pet` | `reset_pet_action` | action code (app uses 1 for “resume after pet”) |
| `lapse` | `lapse_action` | timelapse control |
| `play_sound` | `soundId` | sound playback |
| `start_live` / `start_rtm` / `open_camera` | RTC/license/definition/duration | camera streaming |
| `ble_relay_update` | `update_action`, `connect_action` | BLE relay |
| `privacy`, `discern`, ... | — | auxiliary commands |

`dispatch_handler_start_action()` itself only forwards the code to the HMI; it
does not validate it. The meanings above are confirmed from the app's own
`T5HomePresenter.controlDevice()` calls and the T6/T5 shared presenters.

## Values that **cannot** be set over MQTT

These appear in device info/state reports or are sent by the app, but ctrl_t5
has no `property/set` parser branch for them (or they are state only):

| Key | Why |
|---|---|
| `shareOpen`, `multiConfig`, `autoUpgrade` | Device/cloud meta, no parser |
| `typeCode`, `serviceStatus`, `hertz`, `nextTick` | Reported meta |
| `capacity` | Only the cloud-service parser updates it; not a user setting |
| `softMode`, `stopTime`, `deodor_tip_en`, `preLive` | Present in `/dev_only_iot_device_info` / app payloads, no parser. The app sends `preLive`, but ctrl_t5 ignores it. |
| `cameraConfig`, `lightConfig`, `disturbConfig` | The app appends these to range writes, but ctrl_t5 has **no strings for them at all**; it derives the mode from the range array. |
| `cameraOff`, `cameraInward`, `noSound`, `homeMode`, `smartFrame` | T6-only / not present in ctrl_t5 |
| `packageStandard`, `bag_tip_en`, `spary_tip_en`, `garbageNotify`, `litterFullNotify`, `weightPopup` | Not present in ctrl_t5 |
| `autoProduct`, `highlight`, `liveEncrypt`, `feedPicture` | App privacy keys, no ctrl_t5 parser |
| `moveDetection` | The T6 name; ctrl_t5 uses `moveTracking` |
| `timestamp_enable` | Log-only name; the wire key is `timeDisplay` |
| `toiletDetection_Enable`, `pet_det_Enable`, `voiceDetection`, `move_det_trackEnable` | Log-only names; the wire keys are `toiletDetection`, `petDetection`, `voice`, `moveTracking` |
| `schedule` via the app | The app uses cloud HTTP (`/dev_schedule_get`, `saveFixTimeSetting`), but ctrl_t5 itself *does* accept the `schedule` property. Localkit stores it read-only for now. |
| `n50Durability`, `n60Durability` | Localkit bookkeeping only. The real N50/N60 resets are cloud HTTP routes (`N50Reset`, `purificationReset`), not MQTT services. |
| All state fields (`workState`, `workMode`, `sprayState`, `liquid`, `sandPercent`, `sandWeight`, `boxState`, `lightState`, `wifi`, …) | Reported, never written |

## Firmware evidence

| Evidence | Location |
|---|---|
| Normal parser key table | `parse_recv_property_set_normal()` @ `0x004419b0` (strings around file offset `0x4d9xxx`–`0x4da7xx`) |
| Algorithm parser | `parse_recv_property_set_algo_param()` @ `0x00440950` |
| Feed parser | `parse_recv_property_set_feed_param()` @ `0x0043ef84` |
| Cloud parser | `parse_recv_property_cloud_service()` @ `0x004455fc` |
| Service parser | `parse_service_invoke_msg()` @ `0x00449d9c` |
| Start action forwarder | `dispatch_handler_start_action()` @ `0x0040fafc` |
| Camera range parser | `pk_parse_cameraMultiNew_func()` @ `0x00454fac` |
| Schedule normalizer | `fix_schedule_format()` @ `0x00441578` |
| Trash-bag auth stub | `dispatch_handler_trash_bag_auth()` @ `0x004521bc` returns 0 unconditionally |

## Practical implications for Localkit

- `propertyChange()` must forward the **exact** wire key. The T5 differs from
  the T6 for the timestamp overlay (`timeDisplay`) and night vision (`night`).
- `fixedTimeClear` / `fixedTimeSpray` are writable booleans; the schedule
  itself is not exposed by the Localkit UI yet.
- `cameraMultiRange` is an object array, not a simple pair list. Sending the
  T6-style `[[from,till]]` array will be rejected/ignored.
- The app's extra `cameraConfig` / `lightConfig` / `disturbConfig` keys are
  harmless but useless; do not model them as settings.
- N50/N60 resets are local reminders only. Do not expect an MQTT command to
  reset the physical counters.
