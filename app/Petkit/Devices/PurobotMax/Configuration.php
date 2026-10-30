<?php

namespace App\Petkit\Devices\PurobotMax;

use App\DTOs\DeviceConfigurationDTO;
use App\DTOs\MultiRangeDTO;
use App\Homeassistant\BinarySensor;
use App\Homeassistant\Button;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

/**
 * PetKit Purobot Max (T5) - a camera equipped self-cleaning litter box with
 * the N50 purifying-liquid spray module and the N60 deodorizer cartridge.
 *
 * The property names below are the exact keys ctrl_t5's
 * parse_recv_property_set_normal() / parse_recv_property_set_algo_param()
 * read out of the `property` object of thing/service/property/set. They are
 * also the names propertyChange() forwards, so do not rename a property
 * without checking the firmware string first - see docs/t5-ctrl-mqtt.md for
 * the full settable/read-only matrix.
 *
 * Notable wire-name corrections against the T6 implementation:
 *  - the timestamp overlay is `timeDisplay`, not `timestamp_enable`
 *    (ctrl_t5 logs "set timestamp_enable" but reads `timeDisplay`);
 *  - night vision is `night`, not `infrared`;
 *  - the app sends `cameraConfig`, `lightConfig` and `disturbConfig` with
 *    the range arrays, but ctrl_t5 has no strings for those keys and
 *    ignores them;
 *  - `cameraMultiRange` is an array of objects
 *    ({enable, rpt, time:[[from,till],...]}), not a simple pair list.
 */
class Configuration extends DeviceConfigurationDTO implements ConfigurationInterface
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
        technicalName: 'last_used_by',
        name: 'Last Used By',
        icon: 'mdi:paw',
        valueTemplate: '{{ value_json.states.lastUsedByName }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $lastUsedByName;

    public ?int $lastUsedByPetId;

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

    // Internal only: the currently running workMode (0 clean, 1 dump,
    // 3 recover, 4 smooth, 5 resume) used by pause/continue/terminate to
    // echo the same code back as stop_action/continue_action/end_action.
    public ?int $workMode;

    #[BinarySensor(
        technicalName: 'lightning',
        name: 'Light',
        icon: 'mdi:lightbulb',
        deviceClass: 'light',
        valueTemplate: '{{ value_json.states.lightning }}',
        entityCategory: 'diagnostic',
        payloadOn: '1',
        payloadOff: '0'
    )]
    public bool $lightning;

    // Meta reported by /dev_only_iot_device_info. None of these is a
    // property/set key on ctrl_t5 - they are read-only here, exactly like
    // the T6 implementation.
    #[BinarySensor(
        technicalName: 'share_open',
        name: 'Share Open',
        icon: 'mdi:toggle-switch',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.shareOpen }}',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $shareOpen;

    #[BinarySensor(
        technicalName: 'multi_config',
        name: 'Multi Config',
        icon: 'mdi:toggle-switch',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.multiConfig }}',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $multiConfig;

    #[BinarySensor(
        technicalName: 'auto_upgrade',
        name: 'Auto Upgrade',
        icon: 'mdi:cloud-download',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.autoUpgrade }}',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $autoUpgrade;

    #[Sensor(
        technicalName: 'type_code',
        name: 'Type Code',
        icon: 'mdi:identifier',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.typeCode }}'
    )]
    public int $typeCode;

    #[Sensor(
        technicalName: 'service_status',
        name: 'Service Status',
        icon: 'mdi:information-outline',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.serviceStatus }}'
    )]
    public int $serviceStatus;

    #[Sensor(
        technicalName: 'hertz',
        name: 'Hertz',
        icon: 'mdi:repeat',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.hertz }}'
    )]
    public int $hertz;

    // Camera
    #[HASwitch(
        technicalName: 'camera',
        name: 'Camera Switch',
        commandTopic: 'setting/set',
        icon: 'mdi:camera',
        valueTemplate: '{{ value_json.settings.camera }}',
        commandTemplate: '{"camera":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $camera;

    #[HASwitch(
        technicalName: 'microphone',
        name: 'Microphone',
        commandTopic: 'setting/set',
        icon: 'mdi:microphone',
        valueTemplate: '{{ value_json.settings.microphone }}',
        commandTemplate: '{"microphone":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
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
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $night;

    #[HASwitch(
        technicalName: 'time_display',
        name: 'Timestamp Overlay',
        commandTopic: 'setting/set',
        icon: 'mdi:clock-digital',
        valueTemplate: '{{ value_json.settings.timeDisplay }}',
        commandTemplate: '{"timeDisplay":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $timeDisplay;

    #[HASwitch(
        technicalName: 'camera_light',
        name: 'Camera Light',
        commandTopic: 'setting/set',
        icon: 'mdi:track-light',
        valueTemplate: '{{ value_json.settings.cameraLight }}',
        commandTemplate: '{"cameraLight":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $cameraLight;

    // Camera working-time windows. ctrl_t5's pk_parse_cameraMultiNew_func
    // expects objects with `rpt` (weekdays 1-7) and `time` ([[from,till],...]);
    // `enable` is accepted but ignored by the firmware. Kept as a raw array
    // because this is not a simple pair list like the other ranges.
    public array $cameraMultiRange;

    // The app sends preLive on the privacy screen, but ctrl_t5 has no
    // parser branch for it - read-only here.
    public bool $preLive;

    // Intelligent detection
    #[HASwitch(
        technicalName: 'pet_detection',
        name: 'Pet Appearance Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:cat',
        valueTemplate: '{{ value_json.settings.petDetection }}',
        commandTemplate: '{"petDetection":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $petDetection;

    #[HASwitch(
        technicalName: 'move_tracking',
        name: 'Move Tracking',
        commandTopic: 'setting/set',
        icon: 'mdi:motion-sensor',
        valueTemplate: '{{ value_json.settings.moveTracking }}',
        commandTemplate: '{"moveTracking":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $moveTracking;

    #[Select(
        technicalName: 'move_sensitivity',
        name: 'Move Sensitivity',
        options: ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        commandTopic: 'setting/set',
        icon: 'mdi:tune',
        valueTemplate: '{{ value_json.settings.moveSensitivity }}',
        commandTemplate: '{"moveSensitivity":{{ value }}}',
        entityCategory: 'config'
    )]
    public int $moveSensitivity;

    #[HASwitch(
        technicalName: 'pet_tracking',
        name: 'Pet Tracking',
        commandTopic: 'setting/set',
        icon: 'mdi:crosshairs-gps',
        valueTemplate: '{{ value_json.settings.petTracking }}',
        commandTemplate: '{"petTracking":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $petTracking;

    #[Select(
        technicalName: 'pet_sensitivity',
        name: 'Pet Sensitivity',
        options: ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        commandTopic: 'setting/set',
        icon: 'mdi:tune-variant',
        valueTemplate: '{{ value_json.settings.petSensitivity }}',
        commandTemplate: '{"petSensitivity":{{ value }}}',
        entityCategory: 'config'
    )]
    public int $petSensitivity;

    #[HASwitch(
        technicalName: 'toilet_detection',
        name: 'Toilet Use Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:toilet',
        valueTemplate: '{{ value_json.settings.toiletDetection }}',
        commandTemplate: '{"toiletDetection":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toiletDetection;

    #[HASwitch(
        technicalName: 'ph_detection',
        name: 'pH Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:test-tube',
        valueTemplate: '{{ value_json.settings.phDetection }}',
        commandTemplate: '{"phDetection":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $phDetection;

    #[HASwitch(
        technicalName: 'voice',
        name: 'AI Voice Messages',
        commandTopic: 'setting/set',
        icon: 'mdi:account-voice',
        valueTemplate: '{{ value_json.settings.voice }}',
        commandTemplate: '{"voice":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: 1,
        stateOff: 0,
        entityCategory: 'config'
    )]
    public int $voice;

    #[Number(
        technicalName: 'detect_interval',
        name: 'Detection Interval',
        commandTopic: 'setting/set',
        icon: 'mdi:timer-sand',
        unitOfMeasurement: 's',
        valueTemplate: '{{ value_json.settings.detectInterval }}',
        commandTemplate: '{"detectInterval":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 3600,
        step: 1
    )]
    public int $detectInterval;

    // Detection working-time windows, 5 [from,till] pairs - same shape as
    // the light/tone/do-not-disturb ranges.
    public MultiRangeDTO $detectMultiRange;

    // Lights
    #[HASwitch(
        technicalName: 'light_mode',
        name: 'Set Screen Display',
        commandTopic: 'setting/set',
        icon: 'mdi:brightness-6',
        valueTemplate: '{{ value_json.settings.lightMode }}',
        commandTemplate: '{"lightMode":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $lightMode;

    public MultiRangeDTO $lightMultiRange;

    #[HASwitch(
        technicalName: 'light_assist',
        name: 'Light Assist for Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:lightbulb-auto',
        valueTemplate: '{{ value_json.settings.lightAssist }}',
        commandTemplate: '{"lightAssist":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $lightAssist;

    #[HASwitch(
        technicalName: 'toilet_light',
        name: 'Toilet Light',
        commandTopic: 'setting/set',
        icon: 'mdi:lightbulb',
        valueTemplate: '{{ value_json.settings.toiletLight }}',
        commandTemplate: '{"toiletLight":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toiletLight;

    // Cleaning
    #[HASwitch(
        technicalName: 'auto_work',
        name: 'Auto Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:broom',
        valueTemplate: '{{ value_json.settings.autoWork }}',
        commandTemplate: '{"autoWork":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $autoWork;

    #[Select(
        technicalName: 'auto_interval_min',
        name: 'Time Interval Of Each Cleaning',
        options: ['0', '300', '600', '900', '1800', '2700', '3600', '4500', '5400', '6300', '7200'],
        commandTopic: 'setting/set',
        icon: 'mdi:timer-outline',
        valueTemplate: '{{ value_json.settings.autoIntervalMin }}',
        commandTemplate: '{"autoIntervalMin":{{ value }}}',
        entityCategory: 'config'
    )]
    public int $autoIntervalMin;

    // The app's scheduled-clean screen only sets this enable flag; the
    // schedule itself is managed by PetKit's cloud API. ctrl_t5 does accept
    // `schedule` directly, but Localkit has no schedule editor for it yet.
    #[HASwitch(
        technicalName: 'fixed_time_clear',
        name: 'Scheduled Cleaning Enabled',
        commandTopic: 'setting/set',
        icon: 'mdi:calendar-clock',
        valueTemplate: '{{ value_json.settings.fixedTimeClear }}',
        commandTemplate: '{"fixedTimeClear":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $fixedTimeClear;

    public array $schedule;

    #[HASwitch(
        technicalName: 'avoid_repeat',
        name: 'Avoid Repeated Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:repeat',
        valueTemplate: '{{ value_json.settings.avoidRepeat }}',
        commandTemplate: '{"avoidRepeat":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $avoidRepeat;

    #[HASwitch(
        technicalName: 'underweight',
        name: 'Disable Auto Cleaning For Light Weight',
        commandTopic: 'setting/set',
        icon: 'mdi:scale',
        valueTemplate: '{{ value_json.settings.underweight }}',
        commandTemplate: '{"underweight":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $underweight;

    #[HASwitch(
        technicalName: 'kitten',
        name: 'Kitten Protection',
        commandTopic: 'setting/set',
        icon: 'mdi:cat',
        valueTemplate: '{{ value_json.settings.kitten }}',
        commandTemplate: '{"kitten":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $kitten;

    #[HASwitch(
        technicalName: 'bury',
        name: 'Waste Covering',
        commandTopic: 'setting/set',
        icon: 'mdi:broom',
        valueTemplate: '{{ value_json.settings.bury }}',
        commandTemplate: '{"bury":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $bury;

    #[HASwitch(
        technicalName: 'deep_clean',
        name: 'Deep Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:broom',
        valueTemplate: '{{ value_json.settings.deepClean }}',
        commandTemplate: '{"deepClean":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $deepClean;

    #[HASwitch(
        technicalName: 'tumbling',
        name: 'Tumbling',
        commandTopic: 'setting/set',
        icon: 'mdi:rotate-3d-variant',
        valueTemplate: '{{ value_json.settings.tumbling }}',
        commandTemplate: '{"tumbling":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $tumbling;

    #[Number(
        technicalName: 'lightest',
        name: 'Minimum Weight',
        commandTopic: 'setting/set',
        icon: 'mdi:scale',
        unitOfMeasurement: 'g',
        valueTemplate: '{{ value_json.settings.lightest }}',
        commandTemplate: '{"lightest":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 10000,
        step: 1
    )]
    public int $lightest;

    #[Select(
        technicalName: 'still_time',
        name: 'Delayed Cleaning',
        options: ['0', '30', '60', '120', '180', '240', '300', '600', '900', '1800', '2700', '3600'],
        commandTopic: 'setting/set',
        icon: 'mdi:timer-outline',
        valueTemplate: '{{ value_json.settings.stillTime }}',
        commandTemplate: '{"stillTime":{{ value }}}',
        entityCategory: 'config'
    )]
    public int $stillTime;

    #[Select(
        technicalName: 'sand_type',
        name: 'Litter Type',
        options: ['0', '1', '2', '3'],
        commandTopic: 'setting/set',
        icon: 'mdi:grain',
        valueTemplate: '{{ value_json.settings.sandType }}',
        commandTemplate: '{"sandType":{{ value }}}',
        entityCategory: 'config'
    )]
    public int $sandType;

    #[Select(
        technicalName: 'unit',
        name: 'Weight Unit',
        options: ['0', '1'],
        commandTopic: 'setting/set',
        icon: 'mdi:scale-balance',
        valueTemplate: '{{ value_json.settings.unit }}',
        commandTemplate: '{"unit":{{ value }}}',
        entityCategory: 'config'
    )]
    public int $unit;

    #[HASwitch(
        technicalName: 'sand_saving',
        name: 'Litter Saving',
        commandTopic: 'setting/set',
        icon: 'mdi:leaf',
        valueTemplate: '{{ value_json.settings.sandSaving }}',
        commandTemplate: '{"sandSaving":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $sandSaving;

    #[HASwitch(
        technicalName: 'disturb_mode',
        name: 'Do Not Disturb (Cleaning)',
        commandTopic: 'setting/set',
        icon: 'mdi:bell-off',
        valueTemplate: '{{ value_json.settings.disturbMode }}',
        commandTemplate: '{"disturbMode":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $disturbMode;

    public MultiRangeDTO $distrubMultiRange;

    // Device-internal, not exposed by the vendor app either.
    public array $sandSetUseConfig;
    public array $sandFullWeight;

    public int $downpos;
    public bool $softMode;
    public int $stopTime;

    // Speech
    #[HASwitch(
        technicalName: 'tone_mode',
        name: 'Voice Prompts Do Not Disturb',
        commandTopic: 'setting/set',
        icon: 'mdi:volume-off',
        valueTemplate: '{{ value_json.settings.toneMode }}',
        commandTemplate: '{"toneMode":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toneMode;

    public MultiRangeDTO $toneMultiRange;

    #[HASwitch(
        technicalName: 'system_sound_enable',
        name: 'System Voice Announcements',
        commandTopic: 'setting/set',
        icon: 'mdi:bullhorn',
        valueTemplate: '{{ value_json.settings.systemSoundEnable }}',
        commandTemplate: '{"systemSoundEnable":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $systemSoundEnable;

    #[HASwitch(
        technicalName: 'sound_enable',
        name: 'Sound',
        commandTopic: 'setting/set',
        icon: 'mdi:volume-high',
        valueTemplate: '{{ value_json.settings.soundEnable }}',
        commandTemplate: '{"soundEnable":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $soundEnable;

    #[Select(
        technicalName: 'volume',
        name: 'Volume',
        options: ['1', '2', '3', '4', '5', '6', '7', '8', '9'],
        commandTopic: 'setting/set',
        icon: 'mdi:volume-medium',
        valueTemplate: '{{ value_json.settings.volume }}',
        commandTemplate: '{"volume":{{ value }}}',
        entityCategory: 'config'
    )]
    public int $volume;

    #[Number(
        technicalName: 'selected_sound',
        name: 'Selected Sound',
        commandTopic: 'setting/set',
        icon: 'mdi:music-note',
        valueTemplate: '{{ value_json.settings.selectedSound }}',
        commandTemplate: '{"selectedSound":{{ value }}}',
        entityCategory: 'config',
        min: -1,
        max: 100,
        step: 1
    )]
    public int $selectedSound;

    // Raw sound list accepted by ctrl_t5 (`soundList`). Not exposed to HA.
    public array $soundList;

    // Lock
    #[HASwitch(
        technicalName: 'manual_lock',
        name: 'Child Lock',
        commandTopic: 'setting/set',
        icon: 'mdi:lock',
        valueTemplate: '{{ value_json.settings.manualLock }}',
        commandTemplate: '{"manualLock":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $manualLock;

    #[HASwitch(
        technicalName: 'click_ok_enable',
        name: 'OK Button',
        commandTopic: 'setting/set',
        icon: 'mdi:gesture-tap-button',
        valueTemplate: '{{ value_json.settings.clickOkEnable }}',
        commandTemplate: '{"clickOkEnable":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $clickOkEnable;

    // N50 purifying liquid / spray module
    #[HASwitch(
        technicalName: 'auto_spray',
        name: 'Auto Deodorizing',
        commandTopic: 'setting/set',
        icon: 'mdi:spray',
        valueTemplate: '{{ value_json.settings.autoSpray }}',
        commandTemplate: '{"autoSpray":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $autoSpray;

    #[HASwitch(
        technicalName: 'deep_spray',
        name: 'Deep Deodorizing',
        commandTopic: 'setting/set',
        icon: 'mdi:spray',
        valueTemplate: '{{ value_json.settings.deepSpray }}',
        commandTemplate: '{"deepSpray":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $deepSpray;

    #[HASwitch(
        technicalName: 'fixed_time_spray',
        name: 'Scheduled Deodorizing Enabled',
        commandTopic: 'setting/set',
        icon: 'mdi:calendar-clock',
        valueTemplate: '{{ value_json.settings.fixedTimeSpray }}',
        commandTemplate: '{"fixedTimeSpray":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $fixedTimeSpray;

    #[Sensor(
        technicalName: 'spray_days',
        name: 'Spray Counter',
        icon: 'mdi:counter',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.sprayDays }}'
    )]
    public int $sprayDays;

    #[BinarySensor(
        technicalName: 'deodor_tip_en',
        name: 'Deodorant Reminder',
        icon: 'mdi:bell-ring',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.deodor_tip_en }}',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $deodor_tip_en;

    #[Number(
        technicalName: 'n50_durability',
        name: 'N50 Durability (days)',
        commandTopic: 'setting/set',
        icon: 'mdi:water',
        valueTemplate: '{{ value_json.consumables.n50Durability }}',
        commandTemplate: '{"n50Durability":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    public int $n50Durability;

    #[Sensor(
        technicalName: 'n50_durability_in_days',
        name: 'Next N50 Change in Days',
        icon: 'mdi:update',
        unitOfMeasurement: 'd',
        valueTemplate: '{{ ((value_json.consumables.n50NextChange - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    public mixed $n50NextChange;

    #[Button(
        technicalName: 'action_reset_n50',
        name: 'Reset N50',
        commandTopic: 'action/start',
        icon: 'mdi:water',
        commandTemplate: '{"action": "reset_n50"}',
        availabilityTemplate: 'online',
    )]
    private $actionResetN50 = 1;

    #[Button(
        technicalName: 'action_power_on',
        name: 'Power On',
        commandTopic: 'action/start',
        icon: 'mdi:power',
        commandTemplate: '{"action": "power_on"}',
        availabilityTemplate: 'online',
    )]
    private $actionPowerOn = 1;

    #[Button(
        technicalName: 'action_power_off',
        name: 'Power Off',
        commandTopic: 'action/start',
        icon: 'mdi:power-off',
        commandTemplate: '{"action": "power_off"}',
        availabilityTemplate: 'online',
    )]
    private $actionPowerOff = 1;

    // N60 deodorizer cartridge
    #[Number(
        technicalName: 'n60_durability',
        name: 'N60 Durability (days)',
        commandTopic: 'setting/set',
        icon: 'mdi:diamond-stone',
        valueTemplate: '{{ value_json.consumables.n60Durability }}',
        commandTemplate: '{"n60Durability":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    public int $n60Durability;

    #[Sensor(
        technicalName: 'n60_durability_in_days',
        name: 'Next N60 Change in Days',
        icon: 'mdi:update',
        unitOfMeasurement: 'd',
        valueTemplate: '{{ ((value_json.consumables.n60NextChange - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    public mixed $n60NextChange;

    #[Button(
        technicalName: 'action_reset_n60',
        name: 'Reset N60',
        commandTopic: 'action/start',
        icon: 'mdi:diamond-stone',
        commandTemplate: '{"action": "reset_n60"}',
        availabilityTemplate: 'online',
    )]
    private $actionResetN60 = 1;

    // Privacy / upload
    #[HASwitch(
        technicalName: 'upload',
        name: 'Upload Videos',
        commandTopic: 'setting/set',
        icon: 'mdi:cloud-upload',
        valueTemplate: '{{ value_json.settings.upload }}',
        commandTemplate: '{"upload":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $upload;

    // Device language (locale string, e.g. de_DE). Not exposed to HA.
    public string $language;

    // Cloud-provided capacity/token blob, read-only.
    public array $capacity;

    protected function rules(): array
    {
        return [
            'ipAddress' => ['string'],
            'lastUsedByName' => ['nullable', 'string'],
            'lastUsedByPetId' => ['nullable', 'integer'],
            'workingState' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'workMode' => ['nullable', 'integer'],
            'lightning' => ['bool'],

            'shareOpen' => ['bool'],
            'multiConfig' => ['bool'],
            'autoUpgrade' => ['bool'],
            'typeCode' => ['integer'],
            'serviceStatus' => ['integer'],
            'hertz' => ['integer'],

            'camera' => ['bool'],
            'microphone' => ['bool'],
            'night' => ['bool'],
            'timeDisplay' => ['bool'],
            'cameraLight' => ['bool'],
            'cameraMultiRange' => ['array'],
            'preLive' => ['bool'],

            'petDetection' => ['bool'],
            'moveTracking' => ['bool'],
            'moveSensitivity' => ['integer'],
            'petTracking' => ['bool'],
            'petSensitivity' => ['integer'],
            'toiletDetection' => ['bool'],
            'phDetection' => ['bool'],
            'voice' => ['integer'],
            'detectInterval' => ['integer', 'min:0'],
            'detectMultiRange' => ['array'],

            'lightMode' => ['bool'],
            'lightMultiRange' => ['array'],
            'lightAssist' => ['bool'],
            'toiletLight' => ['bool'],

            'autoWork' => ['bool'],
            'autoIntervalMin' => ['integer', 'min:0'],
            'fixedTimeClear' => ['bool'],
            'schedule' => ['array'],
            'avoidRepeat' => ['bool'],
            'underweight' => ['bool'],
            'kitten' => ['bool'],
            'bury' => ['bool'],
            'deepClean' => ['bool'],
            'tumbling' => ['bool'],
            'lightest' => ['integer', 'min:0'],
            'stillTime' => ['integer', 'min:0'],
            'sandType' => ['integer'],
            'unit' => ['integer', 'in:0,1'],
            'sandSaving' => ['bool'],
            'disturbMode' => ['bool'],
            'distrubMultiRange' => ['array'],
            'sandSetUseConfig' => ['array'],
            'sandFullWeight' => ['array'],
            'downpos' => ['integer'],
            'softMode' => ['bool'],
            'stopTime' => ['integer'],

            'toneMode' => ['bool'],
            'toneMultiRange' => ['array'],
            'systemSoundEnable' => ['bool'],
            'soundEnable' => ['bool'],
            'volume' => ['integer', 'min:0'],
            'selectedSound' => ['integer'],
            'soundList' => ['array'],

            'manualLock' => ['bool'],
            'clickOkEnable' => ['bool'],

            'autoSpray' => ['bool'],
            'deepSpray' => ['bool'],
            'fixedTimeSpray' => ['bool'],
            'sprayDays' => ['integer', 'min:0'],
            'deodor_tip_en' => ['bool'],
            'n50Durability' => ['integer', 'min:0', 'max:90'],
            'n50NextChange' => ['nullable'],

            'n60Durability' => ['integer', 'min:0', 'max:90'],
            'n60NextChange' => ['nullable'],

            'upload' => ['bool'],
            'language' => ['string'],
            'capacity' => ['array'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'ipAddress' => '',
            'lastUsedByName' => null,
            'lastUsedByPetId' => null,
            'workingState' => null,
            'error' => null,
            'workMode' => null,
            'lightning' => false,

            'shareOpen' => false,
            'multiConfig' => true,
            'autoUpgrade' => false,
            'typeCode' => 0,
            'serviceStatus' => 0,
            'hertz' => 0,

            'camera' => true,
            'microphone' => true,
            'night' => true,
            'timeDisplay' => false,
            'cameraLight' => false,
            'cameraMultiRange' => [
                [
                    'enable' => 1,
                    'rpt' => '1,2,3,4,5,6,7',
                    'time' => [[0, 1440]],
                ],
            ],
            'preLive' => false,

            'petDetection' => true,
            'moveTracking' => true,
            'moveSensitivity' => 0,
            'petTracking' => true,
            'petSensitivity' => 0,
            'toiletDetection' => true,
            'phDetection' => true,
            'voice' => 0,
            'detectInterval' => 0,
            'detectMultiRange' => [
                'name' => 'detectMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],

            'lightMode' => true,
            'lightMultiRange' => [
                'name' => 'lightMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],
            'lightAssist' => true,
            'toiletLight' => false,

            'autoWork' => true,
            'autoIntervalMin' => 0,
            'fixedTimeClear' => false,
            'schedule' => [],
            'avoidRepeat' => true,
            'underweight' => false,
            'kitten' => false,
            'bury' => false,
            'deepClean' => false,
            'tumbling' => false,
            'lightest' => 0,
            'stillTime' => 0,
            'sandType' => 0,
            'unit' => 0,
            'sandSaving' => false,
            'disturbMode' => false,
            'distrubMultiRange' => [
                'name' => 'distrubMultiRange',
                'ranges' => [['from' => 40, 'till' => 520]],
            ],
            'sandSetUseConfig' => [[40, 40, 85], [40, 40, 85], [40, 40, 85], [40, 40, 85]],
            'sandFullWeight' => [3500, 5800, 3000, 3500, 3500],
            'downpos' => 0,
            'softMode' => false,
            'stopTime' => 0,

            'toneMode' => false,
            'toneMultiRange' => [
                'name' => 'toneMultiRange',
                'ranges' => [['from' => 1320, 'till' => 360]],
            ],
            'systemSoundEnable' => true,
            'soundEnable' => true,
            'volume' => 7,
            'selectedSound' => -1,
            'soundList' => [],

            'manualLock' => false,
            'clickOkEnable' => true,

            'autoSpray' => true,
            'deepSpray' => false,
            'fixedTimeSpray' => false,
            'sprayDays' => 0,
            'deodor_tip_en' => true,
            'n50Durability' => 30,
            'n50NextChange' => 0,

            'n60Durability' => 45,
            'n60NextChange' => 0,

            'upload' => true,
            'language' => 'en_US',
            'capacity' => [],
        ];
    }

    protected function casts(): array
    {
        return [
            'ipAddress' => new StringCast(),
            'lastUsedByName' => new StringCast(),
            'lastUsedByPetId' => new IntegerCast(),
            'workingState' => new StringCast(),
            'error' => new StringCast(),
            'workMode' => new IntegerCast(),
            'lightning' => new BooleanCast(),

            'shareOpen' => new BooleanCast(),
            'multiConfig' => new BooleanCast(),
            'autoUpgrade' => new BooleanCast(),
            'typeCode' => new IntegerCast(),
            'serviceStatus' => new IntegerCast(),
            'hertz' => new IntegerCast(),

            'camera' => new BooleanCast(),
            'microphone' => new BooleanCast(),
            'night' => new BooleanCast(),
            'timeDisplay' => new BooleanCast(),
            'cameraLight' => new BooleanCast(),
            'cameraMultiRange' => new ArrayCast(),
            'preLive' => new BooleanCast(),

            'petDetection' => new BooleanCast(),
            'moveTracking' => new BooleanCast(),
            'moveSensitivity' => new IntegerCast(),
            'petTracking' => new BooleanCast(),
            'petSensitivity' => new IntegerCast(),
            'toiletDetection' => new BooleanCast(),
            'phDetection' => new BooleanCast(),
            'voice' => new IntegerCast(),
            'detectInterval' => new IntegerCast(),
            'detectMultiRange' => new DTOCast(MultiRangeDTO::class),

            'lightMode' => new BooleanCast(),
            'lightMultiRange' => new DTOCast(MultiRangeDTO::class),
            'lightAssist' => new BooleanCast(),
            'toiletLight' => new BooleanCast(),

            'autoWork' => new BooleanCast(),
            'autoIntervalMin' => new IntegerCast(),
            'fixedTimeClear' => new BooleanCast(),
            'schedule' => new ArrayCast(),
            'avoidRepeat' => new BooleanCast(),
            'underweight' => new BooleanCast(),
            'kitten' => new BooleanCast(),
            'bury' => new BooleanCast(),
            'deepClean' => new BooleanCast(),
            'tumbling' => new BooleanCast(),
            'lightest' => new IntegerCast(),
            'stillTime' => new IntegerCast(),
            'sandType' => new IntegerCast(),
            'unit' => new IntegerCast(),
            'sandSaving' => new BooleanCast(),
            'disturbMode' => new BooleanCast(),
            'distrubMultiRange' => new DTOCast(MultiRangeDTO::class),
            'sandSetUseConfig' => new ArrayCast(),
            'sandFullWeight' => new ArrayCast(),
            'downpos' => new IntegerCast(),
            'softMode' => new BooleanCast(),
            'stopTime' => new IntegerCast(),

            'toneMode' => new BooleanCast(),
            'toneMultiRange' => new DTOCast(MultiRangeDTO::class),
            'systemSoundEnable' => new BooleanCast(),
            'soundEnable' => new BooleanCast(),
            'volume' => new IntegerCast(),
            'selectedSound' => new IntegerCast(),
            'soundList' => new ArrayCast(),

            'manualLock' => new BooleanCast(),
            'clickOkEnable' => new BooleanCast(),

            'autoSpray' => new BooleanCast(),
            'deepSpray' => new BooleanCast(),
            'fixedTimeSpray' => new BooleanCast(),
            'sprayDays' => new IntegerCast(),
            'deodor_tip_en' => new BooleanCast(),
            'n50Durability' => new IntegerCast(),

            'n60Durability' => new IntegerCast(),

            'upload' => new BooleanCast(),
            'language' => new StringCast(),
            'capacity' => new ArrayCast(),
        ];
    }

    public static function fromDevice(Device|BluetoothDevice $device): self
    {
        $config = $device->configuration;
        $data = [];

        $data['workingState'] = $device->working_state ?? null;
        $data['error'] = $device->error ?? null;

        if (isset($config['states'])) {
            $states = $config['states'];
            $data['ipAddress'] = $states['ipAddress'] ?? '';
            $data['lastUsedByPetId'] = $states['lastUsedByPetId'] ?? null;
            $data['lastUsedByName'] = $states['lastUsedByName'] ?? null;
            $data['workMode'] = $states['workMode'] ?? null;
            $data['lightning'] = $states['lightning'] ?? null;
        }

        if (isset($config['settings'])) {
            $settings = $config['settings'];

            $data['shareOpen'] = $settings['shareOpen'] ?? null;
            $data['multiConfig'] = $settings['multiConfig'] ?? null;
            $data['autoUpgrade'] = $settings['autoUpgrade'] ?? null;
            $data['typeCode'] = $settings['typeCode'] ?? null;
            $data['serviceStatus'] = $settings['serviceStatus'] ?? null;
            $data['hertz'] = $settings['hertz'] ?? null;

            $data['camera'] = $settings['camera'] ?? null;
            $data['microphone'] = $settings['microphone'] ?? null;
            $data['night'] = $settings['night'] ?? null;
            $data['timeDisplay'] = $settings['timeDisplay'] ?? null;
            $data['cameraLight'] = $settings['cameraLight'] ?? null;
            $data['cameraMultiRange'] = $settings['cameraMultiRange'] ?? null;
            $data['preLive'] = $settings['preLive'] ?? null;

            $data['petDetection'] = $settings['petDetection'] ?? null;
            $data['moveTracking'] = $settings['moveTracking'] ?? null;
            $data['moveSensitivity'] = $settings['moveSensitivity'] ?? null;
            $data['petTracking'] = $settings['petTracking'] ?? null;
            $data['petSensitivity'] = $settings['petSensitivity'] ?? null;
            $data['toiletDetection'] = $settings['toiletDetection'] ?? null;
            $data['phDetection'] = $settings['phDetection'] ?? null;
            $data['voice'] = $settings['voice'] ?? null;
            $data['detectInterval'] = $settings['detectInterval'] ?? null;
            $data['detectMultiRange'] = $settings['detectMultiRange'] ?? null;

            $data['lightMode'] = $settings['lightMode'] ?? null;
            $data['lightMultiRange'] = $settings['lightMultiRange'] ?? null;
            $data['lightAssist'] = $settings['lightAssist'] ?? null;
            $data['toiletLight'] = $settings['toiletLight'] ?? null;

            $data['autoWork'] = $settings['autoWork'] ?? null;
            $data['autoIntervalMin'] = $settings['autoIntervalMin'] ?? null;
            $data['fixedTimeClear'] = $settings['fixedTimeClear'] ?? null;
            $data['avoidRepeat'] = $settings['avoidRepeat'] ?? null;
            $data['underweight'] = $settings['underweight'] ?? null;
            $data['kitten'] = $settings['kitten'] ?? null;
            $data['bury'] = $settings['bury'] ?? null;
            $data['deepClean'] = $settings['deepClean'] ?? null;
            $data['tumbling'] = $settings['tumbling'] ?? null;
            $data['lightest'] = $settings['lightest'] ?? null;
            $data['stillTime'] = $settings['stillTime'] ?? null;
            $data['sandType'] = $settings['sandType'] ?? null;
            $data['unit'] = $settings['unit'] ?? null;
            $data['sandSaving'] = $settings['sandSaving'] ?? null;
            $data['disturbMode'] = $settings['disturbMode'] ?? null;
            $data['distrubMultiRange'] = $settings['distrubMultiRange'] ?? null;
            $data['sandSetUseConfig'] = $settings['sandSetUseConfig'] ?? null;
            $data['sandFullWeight'] = $settings['sandFullWeight'] ?? null;
            $data['downpos'] = $settings['downpos'] ?? null;
            $data['softMode'] = $settings['softMode'] ?? null;
            $data['stopTime'] = $settings['stopTime'] ?? null;

            $data['toneMode'] = $settings['toneMode'] ?? null;
            $data['toneMultiRange'] = $settings['toneMultiRange'] ?? null;
            $data['systemSoundEnable'] = $settings['systemSoundEnable'] ?? null;
            $data['soundEnable'] = $settings['soundEnable'] ?? null;
            $data['volume'] = $settings['volume'] ?? null;
            $data['selectedSound'] = $settings['selectedSound'] ?? null;
            $data['soundList'] = $settings['soundList'] ?? null;

            $data['manualLock'] = $settings['manualLock'] ?? null;
            $data['clickOkEnable'] = $settings['clickOkEnable'] ?? null;

            $data['autoSpray'] = $settings['autoSpray'] ?? null;
            $data['deepSpray'] = $settings['deepSpray'] ?? null;
            $data['fixedTimeSpray'] = $settings['fixedTimeSpray'] ?? null;
            $data['sprayDays'] = $settings['sprayDays'] ?? null;
            $data['deodor_tip_en'] = $settings['deodor_tip_en'] ?? null;

            $data['upload'] = $settings['upload'] ?? null;
            $data['language'] = $settings['language'] ?? null;
        }

        $data['schedule'] = $config['schedule'] ?? null;
        $data['capacity'] = $config['capacity'] ?? null;

        // An empty range list cannot be represented by MultiRangeDTO (its
        // `ranges` rule is required), so fall back to the default range
        // instead of failing to build the DTO.
        foreach (['lightMultiRange', 'toneMultiRange', 'distrubMultiRange', 'detectMultiRange'] as $rangeKey) {
            if (array_key_exists($rangeKey, $data) && is_array($data[$rangeKey]) && $data[$rangeKey] === []) {
                unset($data[$rangeKey]);
            }
        }

        if (isset($config['consumables'])) {
            $data['n50Durability'] = $config['consumables']['n50Durability'] ?? null;
            $data['n50NextChange'] = $config['consumables']['n50NextChange'] ?? null;
            $data['n60Durability'] = $config['consumables']['n60Durability'] ?? null;
            $data['n60NextChange'] = $config['consumables']['n60NextChange'] ?? null;
        }

        // Keep legitimate empty arrays (detectMultiRange, schedule, capacity)
        // while still ignoring keys the stored config does not have at all.
        $filtered = array_filter($data, fn ($value) => $value !== null);

        return new self($filtered);
    }

    public function toArray(): array
    {
        return [
            'states' => [
                'state' => $this->workingState,
                'error' => $this->error,
                'ipAddress' => $this->ipAddress,
                'lastUsedByPetId' => $this->lastUsedByPetId,
                'lastUsedByName' => $this->lastUsedByName,
                'workMode' => $this->workMode,
                'lightning' => $this->lightning,
            ],
            'settings' => [
                // Meta
                'shareOpen' => $this->shareOpen,
                'multiConfig' => $this->multiConfig,
                'autoUpgrade' => $this->autoUpgrade,
                'typeCode' => $this->typeCode,
                'serviceStatus' => $this->serviceStatus,
                'hertz' => $this->hertz,

                // Camera
                'camera' => $this->camera,
                'microphone' => $this->microphone,
                'night' => $this->night,
                'timeDisplay' => $this->timeDisplay,
                'cameraLight' => $this->cameraLight,
                'cameraMultiRange' => $this->cameraMultiRange,
                'preLive' => $this->preLive,

                // Detection
                'petDetection' => $this->petDetection,
                'moveTracking' => $this->moveTracking,
                'moveSensitivity' => $this->moveSensitivity,
                'petTracking' => $this->petTracking,
                'petSensitivity' => $this->petSensitivity,
                'toiletDetection' => $this->toiletDetection,
                'phDetection' => $this->phDetection,
                'voice' => $this->voice,
                'detectInterval' => $this->detectInterval,
                'detectMultiRange' => $this->detectMultiRange,

                // Lights
                'lightMode' => $this->lightMode,
                'lightMultiRange' => $this->lightMultiRange,
                'lightAssist' => $this->lightAssist,
                'toiletLight' => $this->toiletLight,

                // Cleaning
                'autoWork' => $this->autoWork,
                'autoIntervalMin' => $this->autoIntervalMin,
                'fixedTimeClear' => $this->fixedTimeClear,
                'avoidRepeat' => $this->avoidRepeat,
                'underweight' => $this->underweight,
                'kitten' => $this->kitten,
                'bury' => $this->bury,
                'deepClean' => $this->deepClean,
                'tumbling' => $this->tumbling,
                'lightest' => $this->lightest,
                'stillTime' => $this->stillTime,
                'sandType' => $this->sandType,
                'unit' => $this->unit,
                'sandSaving' => $this->sandSaving,
                'disturbMode' => $this->disturbMode,
                'distrubMultiRange' => $this->distrubMultiRange,
                'sandSetUseConfig' => $this->sandSetUseConfig,
                'sandFullWeight' => $this->sandFullWeight,
                'downpos' => $this->downpos,
                'softMode' => $this->softMode,
                'stopTime' => $this->stopTime,

                // Speech
                'toneMode' => $this->toneMode,
                'toneMultiRange' => $this->toneMultiRange,
                'systemSoundEnable' => $this->systemSoundEnable,
                'soundEnable' => $this->soundEnable,
                'volume' => $this->volume,
                'selectedSound' => $this->selectedSound,
                'soundList' => $this->soundList,

                // Lock
                'manualLock' => $this->manualLock,
                'clickOkEnable' => $this->clickOkEnable,

                // N50 / N60
                'autoSpray' => $this->autoSpray,
                'deepSpray' => $this->deepSpray,
                'fixedTimeSpray' => $this->fixedTimeSpray,
                'sprayDays' => $this->sprayDays,
                'deodor_tip_en' => $this->deodor_tip_en,

                // Privacy / misc
                'upload' => $this->upload,
                'language' => $this->language,
            ],
            'schedule' => $this->schedule,
            'capacity' => $this->capacity,
            'consumables' => [
                'n50Durability' => $this->n50Durability,
                'n50NextChange' => $this->n50NextChange,
                'n60Durability' => $this->n60Durability,
                'n60NextChange' => $this->n60NextChange,
            ],
        ];
    }
}
