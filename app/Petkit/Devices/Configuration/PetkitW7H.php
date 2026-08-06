<?php

namespace App\Petkit\Devices\Configuration;

use App\DTOs\DeviceConfigurationDTO;
use App\Homeassistant\BinarySensor;
use App\Homeassistant\Button;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Image;
use App\Homeassistant\Interfaces\Snapshot;
use App\Homeassistant\Interfaces\Video;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Interfaces\HasCamera;
use Illuminate\Support\Facades\Storage;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

/**
 * Configuration for the PetKit W7H "Eversweet Ultra" - a smart fountain
 * with camera, auto water change and a heater.
 *
 * NOTE: the property names must match the device's setting keys exactly, since
 * {@see \App\Petkit\Devices\PetkitW7H::propertyChange()} diffs the stored
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
class PetkitW7H extends DeviceConfigurationDTO implements ConfigurationInterface, Video, Snapshot, HasCamera
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

    #[BinarySensor(
        technicalName: 'pet_detected',
        name: 'Pet Detected',
        icon: 'mdi:cat',
        deviceClass: 'motion',
        valueTemplate: '{{ value_json.states.petDetected }}',
        entityCategory: 'diagnostic',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $petDetected;

    #[BinarySensor(
        technicalName: 'drink_detected',
        name: 'Drink Detected',
        icon: 'mdi:cup-water',
        valueTemplate: '{{ value_json.states.drinkDetected }}',
        entityCategory: 'diagnostic',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $drinkDetected;

    #[Image(
        technicalName: 'last_snapshot',
        name: 'Snapshot',
    )]
    public ?string $lastSnapshot;

    public ?string $stream;

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

    #[HASwitch(
        technicalName: 'upload',
        name: 'Cloud Recording',
        commandTopic: 'setting/set',
        icon: 'mdi:cloud-upload-outline',
        valueTemplate: '{{ value_json.settings.upload }}',
        commandTemplate: '{"upload":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $upload;

    public array $capacity;
    public array $schedule;

    // Buttons
    #[Button(
        technicalName: 'action_snapshot',
        name: 'Take Snapshot',
        commandTopic: 'action/start',
        icon: 'mdi:camera',
        commandTemplate: '{"action": "snapshot"}',
        availabilityTemplate: 'online',
    )]
    private $actionSnapshot = 1;

    protected function rules(): array
    {
        return [
            // States
            'ipAddress' => ['string'],
            'workingState' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'petDetected' => ['bool'],
            'drinkDetected' => ['bool'],
            'lastSnapshot' => ['nullable', 'string'],
            'stream' => ['nullable', 'string'],

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
            // States
            'ipAddress' => '',
            'workingState' => null,
            'error' => null,
            'petDetected' => false,
            'drinkDetected' => false,
            'lastSnapshot' => null,
            'stream' => null,

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
            // States
            'ipAddress' => new StringCast(),
            'workingState' => new StringCast(),
            'error' => new StringCast(),
            'petDetected' => new BooleanCast(),
            'drinkDetected' => new BooleanCast(),
            'lastSnapshot' => new StringCast(),
            'stream' => new StringCast(),

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

        // Load states
        $data['workingState'] = $device->working_state ?? null;
        $data['error'] = $device->error ?? null;

        if (isset($config['states'])) {
            $states = $config['states'];
            $data['ipAddress'] = $states['ipAddress'] ?? null;
            $data['petDetected'] = $states['petDetected'] ?? null;
            $data['drinkDetected'] = $states['drinkDetected'] ?? null;
            $data['lastSnapshot'] = $states['lastSnapshot'] ?? null;
            $data['stream'] = $states['stream'] ?? null;
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
            'states' => [
                'state' => $this->workingState,
                'error' => $this->error,
                'ipAddress' => $this->ipAddress,
                'lastSnapshot' => $this->lastSnapshot,
                'stream' => $this->stream,
                'petDetected' => $this->petDetected,
                'drinkDetected' => $this->drinkDetected,
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
