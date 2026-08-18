<?php

namespace App\Petkit\Devices\EversweetUltra;

use App\DTOs\DeviceConfigurationDTO;
use App\Homeassistant\BinarySensor;
use App\Homeassistant\Button;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Interfaces\Snapshot;
use App\Homeassistant\Interfaces\Video;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\Interfaces\HasCamera;
use Illuminate\Support\Facades\Storage;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\FloatCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

/**
 * Configuration for the PetKit W7H "Eversweet Ultra" - a smart fountain
 * with camera, auto water change and a heater.
 *
 * NOTE: the property names must match the device's setting keys exactly, since
 * {@see \App\Petkit\Devices\EversweetUltra\Device::propertyChange()} diffs the stored
 * `settings` array and forwards the changed keys straight to the device.
 * Keys marked "(local file only)" in `IMPLEMENT/w7h_config_keys.csv` are not
 * remotely settable over property_set - they are still kept here for parity
 * with the on-device config file, but have no HA command attribute.
 *
 * `heaterTemp` is a rename of the firmware CSV's `targetTemp`: the companion
 * app's W7hSettings model uses the field name `heaterTemp` (paired with
 * `heaterSwitch`), not `targetTemp` - the firmware-side analysis matched on
 * the wrong string and missed the real wire key.
 *
 * Several keys present in the on-device config file (vomitDetection,
 * lightAssist(+range), moveDetection, moveSensitivity, detectInterval,
 * detectMultiRange, soundEnable, wifiLightAssist(+range), voice,
 * selectedSound) are deliberately NOT modelled here: a full dex xref scan of
 * the companion app found zero references to them for the W7H - they are
 * dead/shared-SDK leftovers from other device families (D4sh, T6, T7, AQ1S)
 * and are never exposed to W7H users.
 */
class Configuration extends DeviceConfigurationDTO implements ConfigurationInterface, Video, Snapshot, HasCamera
{
    // States
    #[Sensor(
        technicalName: 'ip_address',
        name: 'IP Address',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.ipAddress }}',
        entityCategory: 'diagnostic'
    )]
    public string $ipAddress;

    // Internal only - not published to Home Assistant itself, but resolved
    // to a name for lastUsedByName below whenever a History row for this
    // device gets a real pet_id (see mergeHistory()).
    public ?int $lastUsedByPetId;

    #[Sensor(
        technicalName: 'last_used_by',
        name: 'Last Used By',
        icon: 'mdi:paw',
        valueTemplate: '{{ value_json.states.lastUsedByName }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $lastUsedByName;

    #[Sensor(
        technicalName: 'device_status',
        name: 'Device Status',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.state }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $workingState;

    #[Sensor(
        technicalName: 'error',
        name: 'Error',
        icon: 'mdi:alert-circle',
        valueTemplate: "{{ 'Ok' if value_json.states.error is none else value_json.states.error }}",
        entityCategory: 'diagnostic'
    )]
    public ?string $error;

    // Not published to Home Assistant - the DTO still needs the property for
    // TakeSnapshot's bookkeeping and the Filament admin preview (UI.php).
    public ?string $lastSnapshot;

    public ?string $stream;

    // Fault flags - IMPLEMENT/w7h_error_states.csv, nested under the wire
    // state's `err` object. Most names are inherited from shared litter-box
    // firmware (tary = waste tray, ptc = heater, cyc = circulation pump);
    // rep* naming is explicitly uncertain per the CSV.
    // tray_door/tray_lock/tray_full, heater_low_water/heater_malfunction,
    // valve_lock/valve_error/valve_normal, pump_lock/pump_malfunction and
    // rep_lock/rep_malfunction are not published to Home Assistant, but the
    // properties stay for parity with the on-device config.
    public bool $taryD;
    public bool $taryL;
    public bool $taryF;

    #[BinarySensor(
        technicalName: 'water_tank_empty',
        name: 'Fresh Water Tank Empty',
        valueTemplate: '{{ value_json.states.taryO }}',
        entityCategory: 'diagnostic',
        deviceClass: 'problem',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $taryO;

    public bool $ptcL;
    public bool $ptcM;
    public bool $valveL;
    public bool $valveE;
    public bool $valveN;
    public bool $cycL;
    public bool $cycM;
    public bool $repL;
    public bool $repM;

    // Install/lock flags - top-level wire state fields. storage_installed/
    // storage_full are not published to Home Assistant (same as above).
    public bool $stgInstall;
    public bool $stgFullState;

    #[BinarySensor(
        technicalName: 'clean_water_tank_installed',
        name: 'Clean Water Tank Installed',
        valueTemplate: '{{ value_json.states.cwtInstall }}',
        entityCategory: 'diagnostic',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $cwtInstall;

    #[BinarySensor(
        technicalName: 'waste_tank_installed',
        name: 'Waste Tank Installed',
        valueTemplate: '{{ value_json.states.wtInstall }}',
        entityCategory: 'diagnostic',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $wtInstall;

    // waste_tank_lock/heater_installed not published to Home Assistant.
    public bool $wtLock;
    public bool $heatInstall;

    // Run-state / status codes - top-level wire state fields, verbatim ints.
    #[Sensor(
        technicalName: 'camera_status',
        name: 'Camera Status Code',
        icon: 'mdi:camera',
        valueTemplate: '{{ value_json.states.cameraStatus }}',
        entityCategory: 'diagnostic'
    )]
    public int $cameraStatus;

    #[Sensor(
        technicalName: 'heat_state',
        name: 'Heater Run State',
        icon: 'mdi:radiator',
        valueTemplate: '{{ value_json.states.heatState }}',
        entityCategory: 'diagnostic'
    )]
    public int $heatState;

    #[Sensor(
        technicalName: 'lift_valve_state',
        name: 'Lift Valve Run State',
        icon: 'mdi:valve',
        valueTemplate: '{{ value_json.states.liftValveState }}',
        entityCategory: 'diagnostic'
    )]
    public int $liftValveState;

    // pump_state not published to Home Assistant.
    public int $pumpState;

    #[Sensor(
        technicalName: 'water_pump_state',
        name: 'Water Pump Run State',
        icon: 'mdi:pump',
        valueTemplate: '{{ value_json.states.waterPumpState }}',
        entityCategory: 'diagnostic'
    )]
    public int $waterPumpState;

    #[Sensor(
        technicalName: 'cwt_state',
        name: 'Clean Water Tank State',
        icon: 'mdi:water',
        valueTemplate: '{{ value_json.states.cwtState }}',
        entityCategory: 'diagnostic'
    )]
    public int $cwtState;

    #[Sensor(
        technicalName: 'wt_state',
        name: 'Waste Tank State',
        icon: 'mdi:water-alert',
        valueTemplate: '{{ value_json.states.wtState }}',
        entityCategory: 'diagnostic'
    )]
    public int $wtState;

    #[Sensor(
        technicalName: 'add_water_state',
        name: 'Add Water State',
        icon: 'mdi:water-plus',
        valueTemplate: '{{ value_json.states.addWaterState }}',
        entityCategory: 'diagnostic'
    )]
    public int $addWaterState;

    #[Sensor(
        technicalName: 'flush_state',
        name: 'Flush State',
        icon: 'mdi:waves',
        valueTemplate: '{{ value_json.states.flushState }}',
        entityCategory: 'diagnostic'
    )]
    public int $flushState;

    #[Sensor(
        technicalName: 'lift_reset_state',
        name: 'Lift Valve Reset State',
        icon: 'mdi:valve',
        valueTemplate: '{{ value_json.states.liftResetState }}',
        entityCategory: 'diagnostic'
    )]
    public int $liftResetState;

    // lift_live_state not published to Home Assistant.
    public int $liftLiveState;

    #[Sensor(
        technicalName: 'disinfect_time',
        name: 'Disinfect Time',
        icon: 'mdi:timer-outline',
        unitOfMeasurement: 's',
        valueTemplate: '{{ value_json.states.disinfectTime }}',
        entityCategory: 'diagnostic'
    )]
    public int $disinfectTime;

    #[Sensor(
        technicalName: 'heat_left_time',
        name: 'Heater Remaining Run Time',
        icon: 'mdi:timer-outline',
        unitOfMeasurement: 's',
        valueTemplate: '{{ value_json.states.heatLeftTime }}',
        entityCategory: 'diagnostic'
    )]
    public int $heatLeftTime;

    #[Sensor(
        technicalName: 'heat_status_time',
        name: 'Time In Current Heat State',
        icon: 'mdi:timer-outline',
        unitOfMeasurement: 's',
        valueTemplate: '{{ value_json.states.heatStatusTime }}',
        entityCategory: 'diagnostic'
    )]
    public int $heatStatusTime;

    #[Sensor(
        technicalName: 'heat_real_temp',
        name: 'Measured Heater Temperature',
        icon: 'mdi:thermometer',
        valueTemplate: '{{ value_json.states.heatRealTemp }}',
        entityCategory: 'diagnostic'
    )]
    public int $heatRealTemp;

    #[Sensor(
        technicalName: 'disinfect_state',
        name: 'Disinfect Cycle State',
        icon: 'mdi:water-check',
        valueTemplate: '{{ value_json.states.disinfectState }}',
        entityCategory: 'diagnostic'
    )]
    public int $disinfectState;

    #[BinarySensor(
        technicalName: 'add_water_frequent',
        name: 'Add Water Too Frequent',
        valueTemplate: '{{ value_json.states.addWaterFrequent }}',
        entityCategory: 'diagnostic',
        deviceClass: 'problem',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $addWaterFrequent;

    // Hall-effect position sensors - nested under the wire state's `sensor`
    // object. Naming (Cover/Door/Lift-Tray) is inherited from shared
    // litter-box firmware; relevance to this fountain is unconfirmed.
    // Not published to Home Assistant (kept in the DTO for parity with the
    // on-device config only).
    public float $hall_CH;
    public float $hall_CL;
    public float $hall_CKL;
    public float $hall_CKR;
    public float $hall_DH;
    public float $hall_DKL;
    public float $hall_DKR;
    public float $hall_LTU;
    public float $hall_LTD;
    public float $hall_TY;

    // Work-state codes - only present on some events (e.g. work_start),
    // nested under the wire state's `workState` object. Mode/Reason/Process
    // are kept in the DTO but not published to Home Assistant.
    public int $workMode;
    public int $workReason;

    // safe_warn not published to Home Assistant.
    public int $safeWarn;

    public int $workProcess;

    // Last error_start event content (IMPLEMENT/w7h_error_states.csv rows
    // 57-59). Not published to Home Assistant.
    public ?string $lastErrorCode;
    public ?string $lastErrorMessage;
    public ?string $lastErrorDetail;

    // Camera settings
    #[HASwitch(
        technicalName: 'camera',
        name: 'Camera',
        commandTopic: 'setting/set',
        icon: 'mdi:camera',
        valueTemplate: '{{ value_json.settings.camera }}',
        commandTemplate: '{"camera":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $camera;

    public array $cameraMultiRange;
    public array $cameraRangeTable;

    #[HASwitch(
        technicalName: 'microphone',
        name: 'Microphone',
        commandTopic: 'setting/set',
        icon: 'mdi:microphone',
        valueTemplate: '{{ value_json.settings.microphone }}',
        commandTemplate: '{"microphone":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $microphone;

    #[HASwitch(
        technicalName: 'night',
        name: 'Night Vision',
        commandTopic: 'setting/set',
        icon: 'mdi:moon-new',
        valueTemplate: '{{ value_json.settings.night }}',
        commandTemplate: '{"night":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $night;

    #[HASwitch(
        technicalName: 'camera_light',
        name: 'Camera Indicator Light',
        commandTopic: 'setting/set',
        icon: 'mdi:led-on',
        valueTemplate: '{{ value_json.settings.cameraLight }}',
        commandTemplate: '{"cameraLight":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $cameraLight;

    #[HASwitch(
        technicalName: 'micro_light',
        name: 'Microphone Indicator Light',
        commandTopic: 'setting/set',
        icon: 'mdi:led-outline',
        valueTemplate: '{{ value_json.settings.microLight }}',
        commandTemplate: '{"microLight":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $microLight;

    // Not remotely settable (local config file only) - kept for parity/local override.
    public bool $timestamp_enable;
    public bool $preLive;
    public bool $log_upload;

    // Indicator light
    #[HASwitch(
        technicalName: 'light_mode',
        name: 'Light Mode',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.lightMode }}',
        commandTemplate: '{"lightMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $lightMode;

    public array $lightMultiRange;
    public array $lightRangeTable;
    public array $toiletLightRangeTable;

    #[HASwitch(
        technicalName: 'manual_lock',
        name: 'Child Lock',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.manualLock }}',
        commandTemplate: '{"manualLock":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $manualLock;

    // Water management
    #[HASwitch(
        technicalName: 'add_water_switch',
        name: 'Refill',
        commandTopic: 'setting/set',
        icon: 'mdi:water-plus',
        valueTemplate: '{{ value_json.settings.addWaterSwitch }}',
        commandTemplate: '{"addWaterSwitch":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $addWaterSwitch;

    #[Number(
        technicalName: 'add_water_mode',
        name: 'Add Water Mode',
        commandTopic: 'setting/set',
        icon: 'mdi:water-sync',
        valueTemplate: '{{ value_json.settings.addWaterMode }}',
        commandTemplate: '{"addWaterMode":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 5,
        step: 1
    )]
    public int $addWaterMode;

    #[HASwitch(
        technicalName: 'auto_water_change',
        name: 'Auto Drain & Refill',
        commandTopic: 'setting/set',
        icon: 'mdi:autorenew',
        valueTemplate: '{{ value_json.settings.autoWaterChange }}',
        commandTemplate: '{"autoWaterChange":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $autoWaterChange;

    #[Number(
        technicalName: 'water_change_cycle',
        name: 'Drain & Refill Cycle',
        commandTopic: 'setting/set',
        icon: 'mdi:calendar-refresh',
        unitOfMeasurement: 'd',
        valueTemplate: '{{ value_json.settings.waterChangeCycle }}',
        commandTemplate: '{"waterChangeCycle":{{ value }}}',
        entityCategory: 'config',
        min: 1,
        max: 30,
        step: 1
    )]
    public int $waterChangeCycle;

    #[Number(
        technicalName: 'water_change_time',
        name: 'Drain & Refill Time',
        commandTopic: 'setting/set',
        icon: 'mdi:clock-outline',
        unitOfMeasurement: 's',
        valueTemplate: '{{ value_json.settings.waterChangeTime }}',
        commandTemplate: '{"waterChangeTime":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 86399,
        step: 60
    )]
    public int $waterChangeTime;

    #[HASwitch(
        technicalName: 'clean_water_lack_light',
        name: 'Clean Water Tank Low',
        commandTopic: 'setting/set',
        icon: 'mdi:water-alert-outline',
        valueTemplate: '{{ value_json.settings.cleanWaterLackLight }}',
        commandTemplate: '{"cleanWaterLackLight":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'diagnostic'
    )]
    public bool $cleanWaterLackLight;

    #[HASwitch(
        technicalName: 'clean_water_empty_light',
        name: 'Clean Water Tank Empty',
        commandTopic: 'setting/set',
        icon: 'mdi:water-off-outline',
        valueTemplate: '{{ value_json.settings.cleanWaterEmptyLight }}',
        commandTemplate: '{"cleanWaterEmptyLight":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'diagnostic'
    )]
    public bool $cleanWaterEmptyLight;

    #[HASwitch(
        technicalName: 'waste_water_full_light',
        name: 'Waste Water Tank Full',
        commandTopic: 'setting/set',
        icon: 'mdi:water-alert',
        valueTemplate: '{{ value_json.settings.wasteWaterFullLight }}',
        commandTemplate: '{"wasteWaterFullLight":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'diagnostic'
    )]
    public bool $wasteWaterFullLight;

    // Fountain / pump ("Flow" in the companion app's English UI)
    #[Select(
        technicalName: 'flush_intensity',
        name: 'Flushing Intensity',
        options: ['1', '2', '3'],
        commandTopic: 'setting/set',
        icon: 'mdi:waves',
        valueTemplate: '{{ value_json.settings.flushIntensity }}',
        commandTemplate: '{"flushIntensity": {{value}}}',
        entityCategory: 'config'
    )]
    public int $flushIntensity;

    #[HASwitch(
        technicalName: 'auto_flush',
        name: 'Auto Drain & Flush',
        commandTopic: 'setting/set',
        icon: 'mdi:pump',
        valueTemplate: '{{ value_json.settings.autoFlush }}',
        commandTemplate: '{"autoFlush":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $autoFlush;

    #[Number(
        technicalName: 'flush_time',
        name: 'Drain & Flush Time',
        commandTopic: 'setting/set',
        icon: 'mdi:clock-outline',
        unitOfMeasurement: 's',
        valueTemplate: '{{ value_json.settings.flushTime }}',
        commandTemplate: '{"flushTime":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 86399,
        step: 60
    )]
    public int $flushTime;

    #[Number(
        technicalName: 'flush_cycle',
        name: 'Drain & Flush Cycle',
        commandTopic: 'setting/set',
        icon: 'mdi:repeat',
        valueTemplate: '{{ value_json.settings.flushCycle }}',
        commandTemplate: '{"flushCycle":{{ value }}}',
        entityCategory: 'config',
        min: 1,
        max: 24,
        step: 1
    )]
    public int $flushCycle;

    // Options inferred from the app's layout view IDs (iv_select_fountain_{off,continuous,interval,sensor})
    // - the real button text was not resolved, only the option order.
    #[Select(
        technicalName: 'fountain_mode',
        name: 'Flow Mode',
        options: ['0', '1', '2', '3'],
        commandTopic: 'setting/set',
        icon: 'mdi:fountain',
        valueTemplate: '{{ value_json.settings.fountainMode }}',
        commandTemplate: '{"fountainMode": {{value}}}',
        entityCategory: 'config'
    )]
    public int $fountainMode;

    #[Number(
        technicalName: 'fountain_time',
        name: 'Flow Run Time Preset',
        commandTopic: 'setting/set',
        icon: 'mdi:timer-outline',
        valueTemplate: '{{ value_json.settings.fountainTime }}',
        commandTemplate: '{"fountainTime":{{ value }}}',
        entityCategory: 'config',
        min: 1,
        max: 10,
        step: 1
    )]
    public int $fountainTime;

    #[Number(
        technicalName: 'sleep_time',
        name: 'Sleep Time Preset',
        commandTopic: 'setting/set',
        icon: 'mdi:sleep',
        valueTemplate: '{{ value_json.settings.sleepTime }}',
        commandTemplate: '{"sleepTime":{{ value }}}',
        entityCategory: 'config',
        min: 1,
        max: 10,
        step: 1
    )]
    public int $sleepTime;

    // Heater
    #[HASwitch(
        technicalName: 'heater_switch',
        name: 'Heater',
        commandTopic: 'setting/set',
        icon: 'mdi:radiator',
        valueTemplate: '{{ value_json.settings.heaterSwitch }}',
        commandTemplate: '{"heaterSwitch":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $heaterSwitch;

    // Stored in deci-Celsius. Firmware CSV flagged this offset (under the name
    // `targetTemp`) as local-file-only, but the app pairs it with heaterSwitch
    // under the field name `heaterTemp` - exposed as settable on that evidence.
    #[Number(
        technicalName: 'heater_temp',
        name: 'Heater Target Temperature',
        commandTopic: 'setting/set',
        icon: 'mdi:thermometer',
        valueTemplate: '{{ value_json.settings.heaterTemp }}',
        commandTemplate: '{"heaterTemp":{{ value }}}',
        entityCategory: 'config',
        min: 150,
        max: 400,
        step: 10
    )]
    public int $heaterTemp;

    // Detection
    #[HASwitch(
        technicalName: 'pet_detection',
        name: 'Pet Appearance Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:cat',
        valueTemplate: '{{ value_json.settings.petDetection }}',
        commandTemplate: '{"petDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $petDetection;

    #[HASwitch(
        technicalName: 'drink_detection',
        name: 'Drinking Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:cup-water',
        valueTemplate: '{{ value_json.settings.drinkDetection }}',
        commandTemplate: '{"drinkDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $drinkDetection;

    // Sound / notifications
    #[HASwitch(
        technicalName: 'system_sound_enable',
        name: 'System Sound',
        commandTopic: 'setting/set',
        icon: 'mdi:desktop-classic',
        valueTemplate: '{{ value_json.settings.systemSoundEnable }}',
        commandTemplate: '{"systemSoundEnable":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $systemSoundEnable;

    #[Number(
        technicalName: 'volume',
        name: 'Volume',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.volume }}',
        commandTemplate: '{"volume":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 100,
        step: 1
    )]
    public int $volume;

    // Do-not-disturb windows
    #[HASwitch(
        technicalName: 'disturb_mode',
        name: 'Do Not Disturb',
        commandTopic: 'setting/set',
        icon: 'mdi:bell-off-outline',
        valueTemplate: '{{ value_json.settings.disturbMode }}',
        commandTemplate: '{"disturbMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $disturbMode;

    public array $distrubMultiRange;

    #[HASwitch(
        technicalName: 'tone_mode',
        name: 'Prompt Tone Do Not Disturb',
        commandTopic: 'setting/set',
        icon: 'mdi:bell-off-outline',
        valueTemplate: '{{ value_json.settings.toneMode }}',
        commandTemplate: '{"toneMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toneMode;

    public array $toneMultiRange;

    #[HASwitch(
        technicalName: 'wl_disturb_mode',
        name: 'Water Level Alert Do Not Disturb',
        commandTopic: 'setting/set',
        icon: 'mdi:bell-off-outline',
        valueTemplate: '{{ value_json.settings.wlDisturbMode }}',
        commandTemplate: '{"wlDisturbMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $wlDisturbMode;

    public array $wlDisturbMultiRange;

    #[HASwitch(
        technicalName: 'aw_disturb_mode',
        name: 'Add Water Alert Do Not Disturb',
        commandTopic: 'setting/set',
        icon: 'mdi:bell-off-outline',
        valueTemplate: '{{ value_json.settings.awDisturbMode }}',
        commandTemplate: '{"awDisturbMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $awDisturbMode;

    public array $awDisturbMultiRange;

    // AI and other settings
    #[HASwitch(
        technicalName: 'smart_frame',
        name: 'Pet Tracking',
        commandTopic: 'setting/set',
        icon: 'mdi:border',
        valueTemplate: '{{ value_json.settings.smartFrame }}',
        commandTemplate: '{"smartFrame":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $smartFrame;

    // upload (Cloud Recording) not published to Home Assistant.
    public bool $upload;

    public array $capacity;
    public array $schedule;

    // Consumables
    #[Number(
        technicalName: 'cube_durability',
        name: 'Cube Durability',
        commandTopic: 'setting/set',
        icon: 'mdi:cube-outline',
        valueTemplate: '{{ value_json.consumables.cubeDurability }}',
        commandTemplate: '{"cubeDurability":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    public int $cubeDurability;

    #[Sensor(
        technicalName: 'cube_durability_in_days',
        name: 'Next Cube Change in Days',
        icon: 'mdi:update',
        unitOfMeasurement: 'd',
        valueTemplate: '{{ ((value_json.consumables.cubeNextChange - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    public int $cubeNextChange;

    // Buttons
    #[Button(
        technicalName: 'action_add_water_reset',
        name: 'Reset Add Water',
        commandTopic: 'action/start',
        icon: 'mdi:water-plus-outline',
        commandTemplate: '{"action": "add_water_reset"}',
        availabilityTemplate: 'online',
    )]
    private $actionAddWaterReset = 1;

    #[Button(
        technicalName: 'action_drain_and_flush',
        name: 'Drain and Flush',
        commandTopic: 'action/start',
        icon: 'mdi:pump',
        commandTemplate: '{"action": "drain_and_flush"}',
        availabilityTemplate: 'online',
    )]
    private $actionDrainAndFlush = 1;

    #[Button(
        technicalName: 'action_refill',
        name: 'Refill',
        commandTopic: 'action/start',
        icon: 'mdi:water-plus',
        commandTemplate: '{"action": "refill"}',
        availabilityTemplate: 'online',
    )]
    private $actionRefill = 1;

    #[Button(
        technicalName: 'action_drain',
        name: 'Drain',
        commandTopic: 'action/start',
        icon: 'mdi:water-minus',
        commandTemplate: '{"action": "drain"}',
        availabilityTemplate: 'online',
    )]
    private $actionDrain = 1;

    #[Button(
        technicalName: 'action_deep_clean',
        name: 'Deep Clean',
        commandTopic: 'action/start',
        icon: 'mdi:spray-bottle',
        commandTemplate: '{"action": "deep_clean"}',
        availabilityTemplate: 'online',
    )]
    private $actionDeepClean = 1;

    #[Button(
        technicalName: 'action_reset_cube',
        name: 'Reset Cube',
        commandTopic: 'action/start',
        icon: 'mdi:cube-outline',
        commandTemplate: '{"action": "reset_cube"}',
        availabilityTemplate: 'online',
    )]
    private $actionResetCube = 1;

    protected function rules(): array
    {
        return [
            // Consumables
            'cubeDurability' => ['integer', 'min:0', 'max:90'],
            'cubeNextChange' => ['integer', 'min:0'],

            // States
            'ipAddress' => ['string'],
            'lastUsedByPetId' => ['nullable', 'integer'],
            'lastUsedByName' => ['nullable', 'string'],
            'workingState' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'lastSnapshot' => ['nullable', 'string'],
            'stream' => ['nullable', 'string'],

            // Fault flags
            'taryD' => ['bool'],
            'taryL' => ['bool'],
            'taryF' => ['bool'],
            'taryO' => ['bool'],
            'ptcL' => ['bool'],
            'ptcM' => ['bool'],
            'valveL' => ['bool'],
            'valveE' => ['bool'],
            'valveN' => ['bool'],
            'cycL' => ['bool'],
            'cycM' => ['bool'],
            'repL' => ['bool'],
            'repM' => ['bool'],

            // Install/lock flags
            'stgInstall' => ['bool'],
            'stgFullState' => ['bool'],
            'cwtInstall' => ['bool'],
            'wtInstall' => ['bool'],
            'wtLock' => ['bool'],
            'heatInstall' => ['bool'],

            // Run-state codes
            'cameraStatus' => ['integer'],
            'heatState' => ['integer'],
            'liftValveState' => ['integer'],
            'pumpState' => ['integer'],
            'waterPumpState' => ['integer'],
            'cwtState' => ['integer'],
            'wtState' => ['integer'],
            'addWaterState' => ['integer'],
            'flushState' => ['integer'],
            'liftResetState' => ['integer'],
            'liftLiveState' => ['integer'],
            'disinfectTime' => ['integer'],
            'heatLeftTime' => ['integer'],
            'heatStatusTime' => ['integer'],
            'heatRealTemp' => ['integer'],
            'disinfectState' => ['integer'],
            'addWaterFrequent' => ['bool'],

            // Hall sensors
            'hall_CH' => ['numeric'],
            'hall_CL' => ['numeric'],
            'hall_CKL' => ['numeric'],
            'hall_CKR' => ['numeric'],
            'hall_DH' => ['numeric'],
            'hall_DKL' => ['numeric'],
            'hall_DKR' => ['numeric'],
            'hall_LTU' => ['numeric'],
            'hall_LTD' => ['numeric'],
            'hall_TY' => ['numeric'],

            // Work state
            'workMode' => ['integer'],
            'workReason' => ['integer'],
            'safeWarn' => ['integer'],
            'workProcess' => ['integer'],

            // Last error event
            'lastErrorCode' => ['nullable', 'string'],
            'lastErrorMessage' => ['nullable', 'string'],
            'lastErrorDetail' => ['nullable', 'string'],

            // Camera
            'camera' => ['bool'],
            'cameraMultiRange' => ['array'],
            'cameraRangeTable' => ['array'],
            'microphone' => ['bool'],
            'night' => ['bool'],
            'cameraLight' => ['bool'],
            'microLight' => ['bool'],

            // Local-file-only
            'timestamp_enable' => ['bool'],
            'preLive' => ['bool'],
            'log_upload' => ['bool'],

            // Indicator light
            'lightMode' => ['bool'],
            'lightMultiRange' => ['array'],
            'lightRangeTable' => ['array'],
            'toiletLightRangeTable' => ['array'],
            'manualLock' => ['bool'],

            // Water management
            'addWaterSwitch' => ['bool'],
            'addWaterMode' => ['integer', 'min:0', 'max:5'],
            'autoWaterChange' => ['bool'],
            'waterChangeCycle' => ['integer', 'min:1', 'max:30'],
            'waterChangeTime' => ['integer', 'min:0', 'max:86399'],
            'cleanWaterLackLight' => ['bool'],
            'cleanWaterEmptyLight' => ['bool'],
            'wasteWaterFullLight' => ['bool'],

            // Fountain / pump
            'flushIntensity' => ['integer', 'min:1', 'max:3'],
            'autoFlush' => ['bool'],
            'flushTime' => ['integer', 'min:0', 'max:86399'],
            'flushCycle' => ['integer', 'min:1', 'max:24'],
            'fountainMode' => ['integer', 'min:0', 'max:3'],
            'fountainTime' => ['integer', 'min:1', 'max:10'],
            'sleepTime' => ['integer', 'min:1', 'max:10'],

            // Heater
            'heaterSwitch' => ['bool'],
            'heaterTemp' => ['integer', 'min:150', 'max:400'],

            // Detection
            'petDetection' => ['bool'],
            'drinkDetection' => ['bool'],

            // Sound
            'systemSoundEnable' => ['bool'],
            'volume' => ['integer', 'min:0', 'max:100'],

            // Do not disturb
            'disturbMode' => ['bool'],
            'distrubMultiRange' => ['array'],
            'toneMode' => ['bool'],
            'toneMultiRange' => ['array'],
            'wlDisturbMode' => ['bool'],
            'wlDisturbMultiRange' => ['array'],
            'awDisturbMode' => ['bool'],
            'awDisturbMultiRange' => ['array'],

            // AI and other
            'smartFrame' => ['bool'],
            'upload' => ['bool'],
            'capacity' => ['array'],
            'schedule' => ['array'],
        ];
    }

    protected function defaults(): array
    {
        return [
            // Consumables
            'cubeDurability' => 30,
            'cubeNextChange' => 0,

            // States
            'ipAddress' => '',
            'lastUsedByPetId' => null,
            'lastUsedByName' => null,
            'workingState' => null,
            'error' => null,
            'lastSnapshot' => null,
            'stream' => null,

            // Fault flags
            'taryD' => false,
            'taryL' => false,
            'taryF' => false,
            'taryO' => false,
            'ptcL' => false,
            'ptcM' => false,
            'valveL' => false,
            'valveE' => false,
            'valveN' => false,
            'cycL' => false,
            'cycM' => false,
            'repL' => false,
            'repM' => false,

            // Install/lock flags
            'stgInstall' => false,
            'stgFullState' => false,
            'cwtInstall' => false,
            'wtInstall' => false,
            'wtLock' => false,
            'heatInstall' => false,

            // Run-state codes
            'cameraStatus' => 0,
            'heatState' => 0,
            'liftValveState' => 0,
            'pumpState' => 0,
            'waterPumpState' => 0,
            'cwtState' => 0,
            'wtState' => 0,
            'addWaterState' => 0,
            'flushState' => 0,
            'liftResetState' => 0,
            'liftLiveState' => 0,
            'disinfectTime' => 0,
            'heatLeftTime' => 0,
            'heatStatusTime' => 0,
            'heatRealTemp' => 0,
            'disinfectState' => 0,
            'addWaterFrequent' => false,

            // Hall sensors
            'hall_CH' => 0,
            'hall_CL' => 0,
            'hall_CKL' => 0,
            'hall_CKR' => 0,
            'hall_DH' => 0,
            'hall_DKL' => 0,
            'hall_DKR' => 0,
            'hall_LTU' => 0,
            'hall_LTD' => 0,
            'hall_TY' => 0,

            // Work state
            'workMode' => 0,
            'workReason' => 0,
            'safeWarn' => 0,
            'workProcess' => 0,

            // Last error event
            'lastErrorCode' => null,
            'lastErrorMessage' => null,
            'lastErrorDetail' => null,

            // Camera
            'camera' => true,
            'cameraMultiRange' => [[0, 1440]],
            'cameraRangeTable' => $this->defaultWeekdayRangeTable([0]),
            'microphone' => true,
            'night' => true,
            'cameraLight' => true,
            'microLight' => true,

            // Local-file-only
            'timestamp_enable' => true,
            'preLive' => false,
            'log_upload' => true,

            // Indicator light
            'lightMode' => true,
            'lightMultiRange' => [[0, 1440]],
            'lightRangeTable' => $this->defaultWeekdayRangeTable([0]),
            'toiletLightRangeTable' => $this->defaultWeekdayRangeTable([]),
            'manualLock' => false,

            // Water management
            'addWaterSwitch' => true,
            'addWaterMode' => 2,
            'autoWaterChange' => true,
            'waterChangeCycle' => 1,
            'waterChangeTime' => 32400,
            'cleanWaterLackLight' => true,
            'cleanWaterEmptyLight' => true,
            'wasteWaterFullLight' => true,

            // Fountain / pump
            'flushIntensity' => 2,
            'autoFlush' => true,
            'flushTime' => 36000,
            'flushCycle' => 1,
            'fountainMode' => 1,
            'fountainTime' => 3,
            'sleepTime' => 3,

            // Heater
            'heaterSwitch' => false,
            'heaterTemp' => 300,

            // Detection
            'petDetection' => true,
            'drinkDetection' => true,

            // Sound
            'systemSoundEnable' => false,
            'volume' => 5,

            // Do not disturb
            'disturbMode' => false,
            'distrubMultiRange' => [[40, 520]],
            'toneMode' => true,
            'toneMultiRange' => [[1320, 360]],
            'wlDisturbMode' => false,
            'wlDisturbMultiRange' => [[1320, 360]],
            'awDisturbMode' => false,
            'awDisturbMultiRange' => [[1320, 360]],

            // AI and other
            'smartFrame' => true,
            'upload' => true,
            'capacity' => [
                ['name' => 'fullVideo', 'workTime' => 0, 'indate' => 0],
                ['name' => 'eventImage', 'workTime' => 0, 'indate' => 0],
                ['name' => 'highLight', 'workTime' => 0, 'indate' => 0],
                ['name' => 'dynamicVideo', 'workTime' => 0, 'indate' => 0],
            ],
            'schedule' => $this->defaultScheduleSlots(),
        ];
    }

    protected function casts(): array
    {
        return [
            // Consumables
            'cubeDurability' => new IntegerCast(),
            'cubeNextChange' => new IntegerCast(),

            // States
            'ipAddress' => new StringCast(),
            'lastUsedByPetId' => new IntegerCast(),
            'lastUsedByName' => new StringCast(),
            'workingState' => new StringCast(),
            'error' => new StringCast(),
            'lastSnapshot' => new StringCast(),
            'stream' => new StringCast(),

            // Fault flags
            'taryD' => new BooleanCast(),
            'taryL' => new BooleanCast(),
            'taryF' => new BooleanCast(),
            'taryO' => new BooleanCast(),
            'ptcL' => new BooleanCast(),
            'ptcM' => new BooleanCast(),
            'valveL' => new BooleanCast(),
            'valveE' => new BooleanCast(),
            'valveN' => new BooleanCast(),
            'cycL' => new BooleanCast(),
            'cycM' => new BooleanCast(),
            'repL' => new BooleanCast(),
            'repM' => new BooleanCast(),

            // Install/lock flags
            'stgInstall' => new BooleanCast(),
            'stgFullState' => new BooleanCast(),
            'cwtInstall' => new BooleanCast(),
            'wtInstall' => new BooleanCast(),
            'wtLock' => new BooleanCast(),
            'heatInstall' => new BooleanCast(),

            // Run-state codes
            'cameraStatus' => new IntegerCast(),
            'heatState' => new IntegerCast(),
            'liftValveState' => new IntegerCast(),
            'pumpState' => new IntegerCast(),
            'waterPumpState' => new IntegerCast(),
            'cwtState' => new IntegerCast(),
            'wtState' => new IntegerCast(),
            'addWaterState' => new IntegerCast(),
            'flushState' => new IntegerCast(),
            'liftResetState' => new IntegerCast(),
            'liftLiveState' => new IntegerCast(),
            'disinfectTime' => new IntegerCast(),
            'heatLeftTime' => new IntegerCast(),
            'heatStatusTime' => new IntegerCast(),
            'heatRealTemp' => new IntegerCast(),
            'disinfectState' => new IntegerCast(),
            'addWaterFrequent' => new BooleanCast(),

            // Hall sensors
            'hall_CH' => new FloatCast(),
            'hall_CL' => new FloatCast(),
            'hall_CKL' => new FloatCast(),
            'hall_CKR' => new FloatCast(),
            'hall_DH' => new FloatCast(),
            'hall_DKL' => new FloatCast(),
            'hall_DKR' => new FloatCast(),
            'hall_LTU' => new FloatCast(),
            'hall_LTD' => new FloatCast(),
            'hall_TY' => new FloatCast(),

            // Work state
            'workMode' => new IntegerCast(),
            'workReason' => new IntegerCast(),
            'safeWarn' => new IntegerCast(),
            'workProcess' => new IntegerCast(),

            // Last error event
            'lastErrorCode' => new StringCast(),
            'lastErrorMessage' => new StringCast(),
            'lastErrorDetail' => new StringCast(),

            // Camera
            'camera' => new BooleanCast(),
            'cameraMultiRange' => new ArrayCast(),
            'cameraRangeTable' => new ArrayCast(),
            'microphone' => new BooleanCast(),
            'night' => new BooleanCast(),
            'cameraLight' => new BooleanCast(),
            'microLight' => new BooleanCast(),

            // Local-file-only
            'timestamp_enable' => new BooleanCast(),
            'preLive' => new BooleanCast(),
            'log_upload' => new BooleanCast(),

            // Indicator light
            'lightMode' => new BooleanCast(),
            'lightMultiRange' => new ArrayCast(),
            'lightRangeTable' => new ArrayCast(),
            'toiletLightRangeTable' => new ArrayCast(),
            'manualLock' => new BooleanCast(),

            // Water management
            'addWaterSwitch' => new BooleanCast(),
            'addWaterMode' => new IntegerCast(),
            'autoWaterChange' => new BooleanCast(),
            'waterChangeCycle' => new IntegerCast(),
            'waterChangeTime' => new IntegerCast(),
            'cleanWaterLackLight' => new BooleanCast(),
            'cleanWaterEmptyLight' => new BooleanCast(),
            'wasteWaterFullLight' => new BooleanCast(),

            // Fountain / pump
            'flushIntensity' => new IntegerCast(),
            'autoFlush' => new BooleanCast(),
            'flushTime' => new IntegerCast(),
            'flushCycle' => new IntegerCast(),
            'fountainMode' => new IntegerCast(),
            'fountainTime' => new IntegerCast(),
            'sleepTime' => new IntegerCast(),

            // Heater
            'heaterSwitch' => new BooleanCast(),
            'heaterTemp' => new IntegerCast(),

            // Detection
            'petDetection' => new BooleanCast(),
            'drinkDetection' => new BooleanCast(),

            // Sound
            'systemSoundEnable' => new BooleanCast(),
            'volume' => new IntegerCast(),

            // Do not disturb
            'disturbMode' => new BooleanCast(),
            'distrubMultiRange' => new ArrayCast(),
            'toneMode' => new BooleanCast(),
            'toneMultiRange' => new ArrayCast(),
            'wlDisturbMode' => new BooleanCast(),
            'wlDisturbMultiRange' => new ArrayCast(),
            'awDisturbMode' => new BooleanCast(),
            'awDisturbMultiRange' => new ArrayCast(),

            // AI and other
            'smartFrame' => new BooleanCast(),
            'upload' => new BooleanCast(),
            'capacity' => new ArrayCast(),
            'schedule' => new ArrayCast(),
        ];
    }

    public static function fromDevice(Device|BluetoothDevice $device): self
    {
        $config = $device->configuration;
        $data = [];

        // Load consumables
        $data['cubeDurability'] = $config['consumables']['cubeDurability'] ?? null;
        $data['cubeNextChange'] = $config['consumables']['cubeNextChange'] ?? null;

        // Load states
        $data['workingState'] = $device->working_state ?? null;
        $data['error'] = $device->error ?? null;

        if (isset($config['states'])) {
            $states = $config['states'];
            $data['ipAddress'] = $states['ipAddress'] ?? null;
            $data['lastUsedByPetId'] = $states['lastUsedByPetId'] ?? null;
            $data['lastUsedByName'] = $states['lastUsedByName'] ?? null;
            $data['lastSnapshot'] = $states['lastSnapshot'] ?? null;
            $data['stream'] = $states['stream'] ?? null;

            // Fault flags
            $data['taryD'] = $states['taryD'] ?? null;
            $data['taryL'] = $states['taryL'] ?? null;
            $data['taryF'] = $states['taryF'] ?? null;
            $data['taryO'] = $states['taryO'] ?? null;
            $data['ptcL'] = $states['ptcL'] ?? null;
            $data['ptcM'] = $states['ptcM'] ?? null;
            $data['valveL'] = $states['valveL'] ?? null;
            $data['valveE'] = $states['valveE'] ?? null;
            $data['valveN'] = $states['valveN'] ?? null;
            $data['cycL'] = $states['cycL'] ?? null;
            $data['cycM'] = $states['cycM'] ?? null;
            $data['repL'] = $states['repL'] ?? null;
            $data['repM'] = $states['repM'] ?? null;

            // Install/lock flags
            $data['stgInstall'] = $states['stgInstall'] ?? null;
            $data['stgFullState'] = $states['stgFullState'] ?? null;
            $data['cwtInstall'] = $states['cwtInstall'] ?? null;
            $data['wtInstall'] = $states['wtInstall'] ?? null;
            $data['wtLock'] = $states['wtLock'] ?? null;
            $data['heatInstall'] = $states['heatInstall'] ?? null;

            // Run-state codes
            $data['cameraStatus'] = $states['cameraStatus'] ?? null;
            $data['heatState'] = $states['heatState'] ?? null;
            $data['liftValveState'] = $states['liftValveState'] ?? null;
            $data['pumpState'] = $states['pumpState'] ?? null;
            $data['waterPumpState'] = $states['waterPumpState'] ?? null;
            $data['cwtState'] = $states['cwtState'] ?? null;
            $data['wtState'] = $states['wtState'] ?? null;
            $data['addWaterState'] = $states['addWaterState'] ?? null;
            $data['flushState'] = $states['flushState'] ?? null;
            $data['liftResetState'] = $states['liftResetState'] ?? null;
            $data['liftLiveState'] = $states['liftLiveState'] ?? null;
            $data['disinfectTime'] = $states['disinfectTime'] ?? null;
            $data['heatLeftTime'] = $states['heatLeftTime'] ?? null;
            $data['heatStatusTime'] = $states['heatStatusTime'] ?? null;
            $data['heatRealTemp'] = $states['heatRealTemp'] ?? null;
            $data['disinfectState'] = $states['disinfectState'] ?? null;
            $data['addWaterFrequent'] = $states['addWaterFrequent'] ?? null;

            // Hall sensors
            $data['hall_CH'] = $states['hall_CH'] ?? null;
            $data['hall_CL'] = $states['hall_CL'] ?? null;
            $data['hall_CKL'] = $states['hall_CKL'] ?? null;
            $data['hall_CKR'] = $states['hall_CKR'] ?? null;
            $data['hall_DH'] = $states['hall_DH'] ?? null;
            $data['hall_DKL'] = $states['hall_DKL'] ?? null;
            $data['hall_DKR'] = $states['hall_DKR'] ?? null;
            $data['hall_LTU'] = $states['hall_LTU'] ?? null;
            $data['hall_LTD'] = $states['hall_LTD'] ?? null;
            $data['hall_TY'] = $states['hall_TY'] ?? null;

            // Work state
            $data['workMode'] = $states['workMode'] ?? null;
            $data['workReason'] = $states['workReason'] ?? null;
            $data['safeWarn'] = $states['safeWarn'] ?? null;
            $data['workProcess'] = $states['workProcess'] ?? null;

            // Last error event
            $data['lastErrorCode'] = $states['lastErrorCode'] ?? null;
            $data['lastErrorMessage'] = $states['lastErrorMessage'] ?? null;
            $data['lastErrorDetail'] = $states['lastErrorDetail'] ?? null;
        }

        // Load settings
        if (isset($config['settings'])) {
            $settings = $config['settings'];

            // Camera
            $data['camera'] = $settings['camera'] ?? null;
            $data['cameraMultiRange'] = $settings['cameraMultiRange'] ?? null;
            $data['cameraRangeTable'] = $settings['cameraRangeTable'] ?? null;
            $data['microphone'] = $settings['microphone'] ?? null;
            $data['night'] = $settings['night'] ?? null;
            $data['cameraLight'] = $settings['cameraLight'] ?? null;
            $data['microLight'] = $settings['microLight'] ?? null;

            // Local-file-only
            $data['timestamp_enable'] = $settings['timestamp_enable'] ?? null;
            $data['preLive'] = $settings['preLive'] ?? null;
            $data['log_upload'] = $settings['log_upload'] ?? null;

            // Indicator light
            $data['lightMode'] = $settings['lightMode'] ?? null;
            $data['lightMultiRange'] = $settings['lightMultiRange'] ?? null;
            $data['lightRangeTable'] = $settings['lightRangeTable'] ?? null;
            $data['toiletLightRangeTable'] = $settings['toiletLightRangeTable'] ?? null;
            $data['manualLock'] = $settings['manualLock'] ?? null;

            // Water management
            $data['addWaterSwitch'] = $settings['addWaterSwitch'] ?? null;
            $data['addWaterMode'] = $settings['addWaterMode'] ?? null;
            $data['autoWaterChange'] = $settings['autoWaterChange'] ?? null;
            $data['waterChangeCycle'] = $settings['waterChangeCycle'] ?? null;
            $data['waterChangeTime'] = $settings['waterChangeTime'] ?? null;
            $data['cleanWaterLackLight'] = $settings['cleanWaterLackLight'] ?? null;
            $data['cleanWaterEmptyLight'] = $settings['cleanWaterEmptyLight'] ?? null;
            $data['wasteWaterFullLight'] = $settings['wasteWaterFullLight'] ?? null;

            // Fountain / pump
            $data['flushIntensity'] = $settings['flushIntensity'] ?? null;
            $data['autoFlush'] = $settings['autoFlush'] ?? null;
            $data['flushTime'] = $settings['flushTime'] ?? null;
            $data['flushCycle'] = $settings['flushCycle'] ?? null;
            $data['fountainMode'] = $settings['fountainMode'] ?? null;
            $data['fountainTime'] = $settings['fountainTime'] ?? null;
            $data['sleepTime'] = $settings['sleepTime'] ?? null;

            // Heater
            $data['heaterSwitch'] = $settings['heaterSwitch'] ?? null;
            $data['heaterTemp'] = $settings['heaterTemp'] ?? $settings['targetTemp'] ?? null;

            // Detection
            $data['petDetection'] = $settings['petDetection'] ?? null;
            $data['drinkDetection'] = $settings['drinkDetection'] ?? null;

            // Sound
            $data['systemSoundEnable'] = $settings['systemSoundEnable'] ?? null;
            $data['volume'] = $settings['volume'] ?? null;

            // Do not disturb
            $data['disturbMode'] = $settings['disturbMode'] ?? null;
            $data['distrubMultiRange'] = $settings['distrubMultiRange'] ?? null;
            $data['toneMode'] = $settings['toneMode'] ?? null;
            $data['toneMultiRange'] = $settings['toneMultiRange'] ?? null;
            $data['wlDisturbMode'] = $settings['wlDisturbMode'] ?? null;
            $data['wlDisturbMultiRange'] = $settings['wlDisturbMultiRange'] ?? null;
            $data['awDisturbMode'] = $settings['awDisturbMode'] ?? null;
            $data['awDisturbMultiRange'] = $settings['awDisturbMultiRange'] ?? null;

            // AI and other
            $data['smartFrame'] = $settings['smartFrame'] ?? null;
            $data['upload'] = $settings['upload'] ?? null;
        }

        // Load schedule and capacity
        $data['schedule'] = $config['schedule'] ?? null;
        $data['capacity'] = $config['capacity'] ?? null;

        // Filter out null values to let defaults() handle missing data
        return new self(array_filter($data, fn($value) => $value !== null));
    }

    public function toArray(): array
    {
        return [
            'consumables' => [
                'cubeDurability' => $this->cubeDurability,
                'cubeNextChange' => $this->cubeNextChange,
            ],
            'states' => [
                'state' => $this->workingState,
                'error' => $this->error,
                'ipAddress' => $this->ipAddress,
                'lastUsedByPetId' => $this->lastUsedByPetId,
                'lastUsedByName' => $this->lastUsedByName,
                'lastSnapshot' => $this->lastSnapshot,
                'stream' => $this->stream,

                // Fault flags
                'taryD' => $this->taryD,
                'taryL' => $this->taryL,
                'taryF' => $this->taryF,
                'taryO' => $this->taryO,
                'ptcL' => $this->ptcL,
                'ptcM' => $this->ptcM,
                'valveL' => $this->valveL,
                'valveE' => $this->valveE,
                'valveN' => $this->valveN,
                'cycL' => $this->cycL,
                'cycM' => $this->cycM,
                'repL' => $this->repL,
                'repM' => $this->repM,

                // Install/lock flags
                'stgInstall' => $this->stgInstall,
                'stgFullState' => $this->stgFullState,
                'cwtInstall' => $this->cwtInstall,
                'wtInstall' => $this->wtInstall,
                'wtLock' => $this->wtLock,
                'heatInstall' => $this->heatInstall,

                // Run-state codes
                'cameraStatus' => $this->cameraStatus,
                'heatState' => $this->heatState,
                'liftValveState' => $this->liftValveState,
                'pumpState' => $this->pumpState,
                'waterPumpState' => $this->waterPumpState,
                'cwtState' => $this->cwtState,
                'wtState' => $this->wtState,
                'addWaterState' => $this->addWaterState,
                'flushState' => $this->flushState,
                'liftResetState' => $this->liftResetState,
                'liftLiveState' => $this->liftLiveState,
                'disinfectTime' => $this->disinfectTime,
                'heatLeftTime' => $this->heatLeftTime,
                'heatStatusTime' => $this->heatStatusTime,
                'heatRealTemp' => $this->heatRealTemp,
                'disinfectState' => $this->disinfectState,
                'addWaterFrequent' => $this->addWaterFrequent,

                // Hall sensors
                'hall_CH' => $this->hall_CH,
                'hall_CL' => $this->hall_CL,
                'hall_CKL' => $this->hall_CKL,
                'hall_CKR' => $this->hall_CKR,
                'hall_DH' => $this->hall_DH,
                'hall_DKL' => $this->hall_DKL,
                'hall_DKR' => $this->hall_DKR,
                'hall_LTU' => $this->hall_LTU,
                'hall_LTD' => $this->hall_LTD,
                'hall_TY' => $this->hall_TY,

                // Work state
                'workMode' => $this->workMode,
                'workReason' => $this->workReason,
                'safeWarn' => $this->safeWarn,
                'workProcess' => $this->workProcess,

                // Last error event
                'lastErrorCode' => $this->lastErrorCode,
                'lastErrorMessage' => $this->lastErrorMessage,
                'lastErrorDetail' => $this->lastErrorDetail,
            ],
            'settings' => [
                // Camera
                'camera' => $this->camera,
                'cameraMultiRange' => $this->cameraMultiRange,
                'cameraRangeTable' => $this->cameraRangeTable,
                'microphone' => $this->microphone,
                'night' => $this->night,
                'cameraLight' => $this->cameraLight,
                'microLight' => $this->microLight,

                // Local-file-only
                'timestamp_enable' => $this->timestamp_enable,
                'preLive' => $this->preLive,
                'log_upload' => $this->log_upload,

                // Indicator light
                'lightMode' => $this->lightMode,
                'lightMultiRange' => $this->lightMultiRange,
                'lightRangeTable' => $this->lightRangeTable,
                'toiletLightRangeTable' => $this->toiletLightRangeTable,
                'manualLock' => $this->manualLock,

                // Water management
                'addWaterSwitch' => $this->addWaterSwitch,
                'addWaterMode' => $this->addWaterMode,
                'autoWaterChange' => $this->autoWaterChange,
                'waterChangeCycle' => $this->waterChangeCycle,
                'waterChangeTime' => $this->waterChangeTime,
                'cleanWaterLackLight' => $this->cleanWaterLackLight,
                'cleanWaterEmptyLight' => $this->cleanWaterEmptyLight,
                'wasteWaterFullLight' => $this->wasteWaterFullLight,

                // Fountain / pump
                'flushIntensity' => $this->flushIntensity,
                'autoFlush' => $this->autoFlush,
                'flushTime' => $this->flushTime,
                'flushCycle' => $this->flushCycle,
                'fountainMode' => $this->fountainMode,
                'fountainTime' => $this->fountainTime,
                'sleepTime' => $this->sleepTime,

                // Heater
                'heaterSwitch' => $this->heaterSwitch,
                'heaterTemp' => $this->heaterTemp,

                // Detection
                'petDetection' => $this->petDetection,
                'drinkDetection' => $this->drinkDetection,

                // Sound
                'systemSoundEnable' => $this->systemSoundEnable,
                'volume' => $this->volume,

                // Do not disturb
                'disturbMode' => $this->disturbMode,
                'distrubMultiRange' => $this->distrubMultiRange,
                'toneMode' => $this->toneMode,
                'toneMultiRange' => $this->toneMultiRange,
                'wlDisturbMode' => $this->wlDisturbMode,
                'wlDisturbMultiRange' => $this->wlDisturbMultiRange,
                'awDisturbMode' => $this->awDisturbMode,
                'awDisturbMultiRange' => $this->awDisturbMultiRange,

                // AI and other
                'smartFrame' => $this->smartFrame,
                'upload' => $this->upload,
            ],
            'schedule' => $this->schedule,
            'capacity' => $this->capacity,
        ];
    }

    public function toSnapshot(): ?string
    {
        if (is_null($this->lastSnapshot)) {
            return null;
        }
        return base64_encode(Storage::disk('snapshots')->get($this->lastSnapshot));
    }

    private function defaultWeekdayRangeTable(array $rangeSub): array
    {
        return array_map(fn (int $wday) => ['wday' => $wday, 'rangeSub' => $rangeSub], range(0, 6));
    }

    private function defaultScheduleSlots(): array
    {
        return array_fill(0, 20, ['id' => 0, 'type' => 0, 'time' => 0, 'repeats' => '']);
    }
}
