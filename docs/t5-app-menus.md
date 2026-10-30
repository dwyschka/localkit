# Purobot Max (T5) — app menu mapping

The T5 (deviceType `21` in `com.petkit.oversea 13.4.1`) shares the T6
settings activities (`petkitBleDevice/t6/*`) but hides several T6 rows and
adds the T5 deodorizing screens.

## T6SettingActivity (main settings)

| App row | Visible on T5 | Localkit section |
|---|---|---|
| Device name / firmware / MAC / SN | yes | Device header |
| Power | yes | action |
| Camera switch | yes | Camera Settings |
| Working time | yes | Camera Settings → Camera Working Time |
| Camera settings (mic/night/timestamp/timezone) | yes | Camera Settings |
| Privacy controls | yes | Privacy |
| Intelligent detection | yes | Intelligent Detection |
| Personal dressing | yes | not modeled (T6 home-mode feature) |
| Clean setting | yes | Set Clean |
| Consumables (litter, box, N60) | yes | Consumables |
| Package (waste bags) | **no** (`llPackage` hidden for 21) | not modeled |
| Home mode | **no** (`lineHomeMode` hidden for 21) | not modeled |
| Deodorization / N60 | yes (`llDeodoration` shown for 21) | Set Clean + Consumables |
| Uninterrupted rotation | **no** | not modeled |
| Speech settings | yes | Speech Settings |
| Child lock | yes | Child Lock |
| Litter type | yes | Device |
| Weight unit | yes | Device |
| Screen light | yes | Device |
| Device language | yes | Device |
| Share | yes | Privacy (read-only) |
| Notification settings | yes | not modeled (cloud-side) |
| OTA / Wi-Fi / delete | yes | standard Localkit |

## T6SmartSettingActivity ("Set Clean")

T5-specific rows present:

- Auto cleaning
- Wait/delayed cleaning (`stillTime`)
- Time interval of each cleaning (`autoIntervalMin`)
- Avoid repeated cleaning
- Underweight / minimum weight
- Kitten protection
- Deep cleaning
- Waste covering (`bury`)
- Litter saving (`sandSaving`)
- Toilet light
- Auto deodorizing (`autoSpray`) — T5 row, hidden on T6 (`deviceType == 27`)
- Deep deodorizing (`deepSpray`)
- Scheduled deodorizing (`fixedTimeSpray`) + `T5RegularDeodorizationActivity`
- Do not disturb (`disturbMode` + `distrubMultiRange`)

T5 hides the T6-only package/bag row.

## Other screens

| Activity | Settings keys |
|---|---|
| `T6LightSettingActivity` | `lightMultiRange` |
| `T6WorkingTimeActivity` | `cameraMultiRange`, `cameraLight`, `cameraConfig` (ignored by ctrl_t5) |
| `T6IntelligentDetectionActivity` | `petDetection`, `toiletDetection`, `smartFrame` (not in T5 ctrl) |
| `T6SpeechSettingsActivity` | `toneMode`, `systemSoundEnable`, `volume`, `selectedSound`, `toneMultiRange` |
| `T6SpeechDoNotDisturbSettingActivity` | `toneMultiRange` |
| `AiPhActivity` | `phDetection` |
| `AiVoiceActivity` | `voice` |
| `KidLockActivity` | `manualLock`, `clickOkEnable` |
| `T6LitterSettingActivity` | `sandType` |
| `T6DeviceLanguageActivity` | `language` |
| `T6PrivacySettingActivity` | `upload`; `preLive`/`highlight`/`liveEncrypt`/`autoProduct` are not T5 ctrl keys |
| `T5RegularDeodorizationActivity` | `fixedTimeSpray` + cloud schedule |
| `T6ConsumablesActivity` | N50/N60 reset (cloud HTTP, not MQTT) |

## Actions (T5 home screen)

`T5BottomView` exposes clean, dump, light, recover, smooth, maintenance and
odor removal. There is no pack/door button. The app's calibration flow uses
`T6CalibrateLitterTwoPresenter.controlDevice(..., 6, 1)`.

| App method | Code / topic |
|---|---|
| `cleanup()` | `start_action: 0` |
| `T5Dump()` | `start_action: 1` |
| `beginDeodorant()` | `start_action: 2` |
| `optionRecover()` | `start_action: 3` |
| `optionSmooth()` | `start_action: 4` |
| calibration flow | `start_action: 6` |
| `lightTurnOn()` / `lightTurnOff()` | `start_action: 7` / `end_action: 7` |
| `startMaintenance()` | `start_action: 9` |
| `stopMaintenance()` | `stop_action: 9` |
| `pauseClean()` / `continueToClean()` / `terminateClean()` | current work mode as `stop_action` / `continue_action` / `end_action` |
| `resumeRun()` | `reset_pet_action: 1` (not exposed as a Localkit button yet) |
