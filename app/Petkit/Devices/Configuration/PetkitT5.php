<?php

namespace App\Petkit\Devices\Configuration;

use App\DTOs\DeviceConfigurationDTO;
use App\DTOs\MultiRangeDTO;
use App\Homeassistant\BinarySensor;
use App\Homeassistant\Button;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Image;
use App\Homeassistant\Interfaces\Snapshot;
use App\Homeassistant\Interfaces\Video;
use App\Homeassistant\Number;
use App\Homeassistant\Sensor;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Interfaces\HasCamera;
use Illuminate\Support\Facades\Storage;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

/**
 * Configuration for the PetKit T5 - a camera equipped self-cleaning litter box
 * with an automatic deodorant spray unit.
 *
 * Field set reverse engineered from the on-device `ctrl_t5` firmware (MIPS) and its
 * `app_conf` dump (`t5.conf`): every settings key below was confirmed present in both
 * the local file parser (`app_config_load`) and/or the remote property_set dispatcher
 * (`parse_recv_property_set_normal` / `parse_recv_property_set_algo_param`).
 * Notable renames confirmed via matching struct store-offsets: `timestamp_enable` (file)
 * is `timeDisplay` on the wire, and `night` shares its offset with the wire alias `infrared`.
 *
 * NOTE: the property names must match the device's setting keys exactly, since
 * {@see \App\Petkit\Devices\PetkitT5::propertyChange()} diffs the stored `settings`
 * array and forwards the changed keys straight to the device.
 */
class PetkitT5 extends DeviceConfigurationDTO implements ConfigurationInterface, Video, Snapshot, HasCamera
{
    public array $schedule;

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
        technicalName: 'move_detected',
        name: 'Move Detected',
        icon: 'mdi:cursor-move',
        deviceClass: 'motion',
        valueTemplate: '{{ value_json.states.moveDetected }}',
        entityCategory: 'diagnostic',
        payloadOn: '1',
        payloadOff: '0'
    )]
    public bool $moveDetected;

    #[BinarySensor(
        technicalName: 'pet_detected',
        name: 'Pet Detected',
        icon: 'mdi:cat',
        deviceClass: 'motion',
        valueTemplate: '{{ value_json.states.petDetected }}',
        entityCategory: 'diagnostic',
        payloadOn: '1',
        payloadOff: '0'
    )]
    public bool $petDetected;

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

    #[Image(
        technicalName: 'last_snapshot',
        name: 'Snapshot',
    )]
    public ?string $lastSnapshot;

    public ?string $stream;

    // Device meta (kept for signup / device info payloads)
    public bool $shareOpen;
    public bool $multiConfig;
    public bool $autoUpgrade;
    public int $typeCode;
    public int $serviceStatus;
    public int $hertz;

    // Display / camera settings
    #[HASwitch(
        technicalName: 'time_display',
        name: 'Timestamp Display',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.timeDisplay }}',
        commandTemplate: '{"timeDisplay":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $timeDisplay;

    #[HASwitch(
        technicalName: 'camera',
        name: 'Camera Switch',
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

    public MultiRangeDTO $cameraMultiRange;

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
        name: 'Camera Light',
        commandTopic: 'setting/set',
        icon: 'mdi:track-light',
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
        technicalName: 'toilet_light',
        name: 'Toilet Light',
        commandTopic: 'setting/set',
        icon: 'mdi:lightbulb',
        valueTemplate: '{{ value_json.settings.toiletLight }}',
        commandTemplate: '{"toiletLight":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toiletLight;

    public bool $preLive;

    // Indicator lights - not in Localkit UI - not exposed to HA
    public bool $lightMode;

    public MultiRangeDTO $lightMultiRange;

    #[HASwitch(
        technicalName: 'light_assist',
        name: 'Light Assist for Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:lightbulb-auto',
        valueTemplate: '{{ value_json.settings.lightAssist }}',
        commandTemplate: '{"lightAssist":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $lightAssist;

    public array $cameraRangeTable;

    // Do not disturb / tone - not in Localkit UI - not exposed to HA
    public bool $toneMode;

    public MultiRangeDTO $toneMultiRange;

    public bool $disturbMode;

    public MultiRangeDTO $distrubMultiRange;

    // Lock
    #[HASwitch(
        technicalName: 'manual_lock',
        name: 'Child Lock',
        commandTopic: 'setting/set',
        icon: 'mdi:lock',
        valueTemplate: '{{ value_json.settings.manualLock }}',
        commandTemplate: '{"manualLock":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $manualLock;

    #[HASwitch(
        technicalName: 'click_ok_enable',
        name: 'Confirm Click Sound',
        commandTopic: 'setting/set',
        icon: 'mdi:gesture-tap',
        valueTemplate: '{{ value_json.settings.clickOkEnable }}',
        commandTemplate: '{"clickOkEnable":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $clickOkEnable;

    // Cleaning behaviour
    #[HASwitch(
        technicalName: 'auto_work',
        name: 'Auto Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:broom',
        valueTemplate: '{{ value_json.settings.autoWork }}',
        commandTemplate: '{"autoWork":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $autoWork;

    public int $autoIntervalMin;
    public int $fixedTimeClear;

    #[HASwitch(
        technicalName: 'avoid_repeat',
        name: 'Avoid Repeated Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:repeat',
        valueTemplate: '{{ value_json.settings.avoidRepeat }}',
        commandTemplate: '{"avoidRepeat":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $avoidRepeat;

    // Not in Localkit UI - not exposed to HA
    public bool $underweight;

    #[HASwitch(
        technicalName: 'kitten',
        name: 'Kitten Protection',
        commandTopic: 'setting/set',
        icon: 'mdi:cat',
        valueTemplate: '{{ value_json.settings.kitten }}',
        commandTemplate: '{"kitten":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $kitten;

    // Not in Localkit UI - not exposed to HA
    public bool $bury;
    public bool $deepClean;
    public bool $downpos;

    #[HASwitch(
        technicalName: 'sand_saving',
        name: 'Litter Saving',
        commandTopic: 'setting/set',
        icon: 'mdi:leaf',
        valueTemplate: '{{ value_json.settings.sandSaving }}',
        commandTemplate: '{"sandSaving":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $sandSaving;

    #[HASwitch(
        technicalName: 'tumbling',
        name: 'Tumbling',
        commandTopic: 'setting/set',
        icon: 'mdi:rotate-3d-variant',
        valueTemplate: '{{ value_json.settings.tumbling }}',
        commandTemplate: '{"tumbling":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $tumbling;
    public int $lightest;
    public int $stillTime;

    // Litter Type - not exposed to HA
    public int $sandType;

    // No weight sensor confirmed on this device - not exposed to HA
    public int $unit;

    // Toilet / spray (deodorant) settings
    #[HASwitch(
        technicalName: 'toilet_detection',
        name: 'Toilet Video Recording',
        commandTopic: 'setting/set',
        icon: 'mdi:toilet',
        valueTemplate: '{{ value_json.settings.toiletDetection }}',
        commandTemplate: '{"toiletDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toiletDetection;

    #[HASwitch(
        technicalName: 'ph_detection',
        name: 'Urine pH Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:water',
        valueTemplate: '{{ value_json.settings.phDetection }}',
        commandTemplate: '{"phDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $phDetection;

    #[HASwitch(
        technicalName: 'soft_mode',
        name: 'Loose Stool Recognition',
        commandTopic: 'setting/set',
        icon: 'mdi:cog',
        valueTemplate: '{{ value_json.settings.softMode }}',
        commandTemplate: '{"softMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $softMode;

    #[HASwitch(
        technicalName: 'auto_spray',
        name: 'Auto Deodorizing',
        commandTopic: 'setting/set',
        icon: 'mdi:spray',
        valueTemplate: '{{ value_json.settings.autoSpray }}',
        commandTemplate: '{"autoSpray":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
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
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $deepSpray;

    public int $fixedTimeSpray;

    #[Number(
        technicalName: 'spray_days',
        name: 'Deodorant Refill Cycle',
        commandTopic: 'setting/set',
        icon: 'mdi:calendar-refresh',
        unitOfMeasurement: 'd',
        valueTemplate: '{{ value_json.settings.sprayDays }}',
        commandTemplate: '{"sprayDays":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    public int $sprayDays;

    // Firmware confirms this local `deodor_tip_en` key, but never reads it from the
    // remote property_set dispatcher - not exposed to HA / not settable remotely.
    public bool $deodorTipEn;

    // Detection
    #[HASwitch(
        technicalName: 'move_detection',
        name: 'Move Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:eye-arrow-left-outline',
        valueTemplate: '{{ value_json.settings.moveDetection }}',
        commandTemplate: '{"moveDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $moveDetection;

    #[Number(
        technicalName: 'move_sensitivity',
        name: 'Move Sensitivity',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.moveSensitivity }}',
        commandTemplate: '{"moveSensitivity":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 9,
        step: 1
    )]
    public int $moveSensitivity;

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

    #[Number(
        technicalName: 'pet_sensitivity',
        name: 'Pet Appearance Sensitivity',
        commandTopic: 'setting/set',
        icon: 'mdi:cat',
        valueTemplate: '{{ value_json.settings.petSensitivity }}',
        commandTemplate: '{"petSensitivity":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 9,
        step: 1
    )]
    public int $petSensitivity;

    public int $detectInterval;
    public array $detectMultiRange;

    // Sound (no speaker confirmed on this device - not exposed to HA)
    public bool $soundEnable;
    public bool $systemSoundEnable;
    public int $volume;
    public int $selectedSound;
    public bool $voice;

    // Cloud upload - not exposed to HA
    public bool $upload;

    // Local-file-only per firmware (no wire/property_set xref) - not exposed to HA
    public bool $logUpload;

    public array $capacity;

    // Actions
    #[Button(
        technicalName: 'action_snapshot',
        name: 'Take Snapshot',
        commandTopic: 'action/start',
        icon: 'mdi:camera',
        commandTemplate: '{"action": "snapshot"}',
        availabilityTemplate: 'online',
    )]
    private $actionSnapshot = 1;

    #[Button(
        technicalName: 'action_cleaning_start',
        name: 'Start Cleaning',
        commandTopic: 'action/start',
        icon: 'mdi:broom',
        commandTemplate: '{"action": "start_cleaning"}',
        availabilityTemplate: '{% if value_json.states.state == "IDLE" %}online{% else %}offline{% endif %}',
    )]
    private $actionCleaning = 1;

    #[Button(
        technicalName: 'action_deodorize',
        name: 'Deodorize',
        commandTopic: 'action/start',
        icon: 'mdi:spray',
        commandTemplate: '{"action": "deodorize"}',
        availabilityTemplate: '{% if value_json.states.state == "IDLE" %}online{% else %}offline{% endif %}',
    )]
    private $actionDeodorize = 1;

    #[Button(
        technicalName: 'action_level',
        name: 'Level',
        commandTopic: 'action/start',
        icon: 'mdi:format-horizontal-align-center',
        commandTemplate: '{"action": "level"}',
        availabilityTemplate: '{% if value_json.states.state == "IDLE" %}online{% else %}offline{% endif %}',
    )]
    private $actionLevel = 1;

    #[Button(
        technicalName: 'action_start_lightning',
        name: 'Start Lightning',
        commandTopic: 'action/start',
        icon: 'mdi:lightbulb-on',
        commandTemplate: '{"action": "start_lightning"}',
        availabilityTemplate: 'online',
    )]
    private $actionStartLightning = 1;

    #[Button(
        technicalName: 'action_stop_lightning',
        name: 'Stop Lightning',
        commandTopic: 'action/start',
        icon: 'mdi:lightbulb-off',
        commandTemplate: '{"action": "stop_lightning"}',
        availabilityTemplate: 'online',
    )]
    private $actionStopLightning = 1;

    protected function rules(): array
    {
        return [
            'schedule' => ['array'],

            // States
            'ipAddress' => ['string'],
            'workingState' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'moveDetected' => ['bool'],
            'petDetected' => ['bool'],
            'lightning' => ['bool'],
            'lastSnapshot' => ['nullable', 'string'],
            'stream' => ['nullable', 'string'],

            // Meta
            'shareOpen' => ['bool'],
            'multiConfig' => ['bool'],
            'autoUpgrade' => ['bool'],
            'typeCode' => ['integer'],
            'serviceStatus' => ['integer'],
            'hertz' => ['integer', 'min:50', 'max:60'],

            // Display / camera
            'timeDisplay' => ['bool'],
            'camera' => ['bool'],
            'cameraMultiRange' => ['array'],
            'microphone' => ['bool'],
            'night' => ['bool'],
            'cameraLight' => ['bool'],
            'toiletLight' => ['bool'],
            'preLive' => ['bool'],

            // Lights
            'lightMode' => ['bool'],
            'lightMultiRange' => ['array'],
            'lightAssist' => ['bool'],
            'cameraRangeTable' => ['array'],

            // Do not disturb
            'toneMode' => ['bool'],
            'toneMultiRange' => ['array'],
            'disturbMode' => ['bool'],
            'distrubMultiRange' => ['array'],

            // Lock
            'manualLock' => ['bool'],
            'clickOkEnable' => ['bool'],

            // Cleaning
            'autoWork' => ['bool'],
            'autoIntervalMin' => ['integer', 'min:0'],
            'fixedTimeClear' => ['integer', 'min:0'],
            'avoidRepeat' => ['bool'],
            'underweight' => ['bool'],
            'kitten' => ['bool'],
            'bury' => ['bool'],
            'deepClean' => ['bool'],
            'downpos' => ['bool'],
            'sandSaving' => ['bool'],
            'tumbling' => ['bool'],
            'lightest' => ['integer', 'min:0'],
            'stillTime' => ['integer', 'min:0'],
            'sandType' => ['integer', 'in:0,1,2'],
            'unit' => ['integer', 'in:0,1'],

            // Toilet / spray
            'toiletDetection' => ['bool'],
            'phDetection' => ['bool'],
            'softMode' => ['bool'],
            'autoSpray' => ['bool'],
            'deepSpray' => ['bool'],
            'fixedTimeSpray' => ['integer', 'min:0'],
            'sprayDays' => ['integer', 'min:0', 'max:90'],
            'deodorTipEn' => ['bool'],

            // Detection
            'moveDetection' => ['bool'],
            'moveSensitivity' => ['integer', 'min:0', 'max:9'],
            'petDetection' => ['bool'],
            'petSensitivity' => ['integer', 'min:0', 'max:9'],
            'detectInterval' => ['integer', 'min:0'],
            'detectMultiRange' => ['array'],

            // Sound
            'soundEnable' => ['bool'],
            'systemSoundEnable' => ['bool'],
            'volume' => ['integer', 'min:0', 'max:9'],
            'selectedSound' => ['integer'],
            'voice' => ['bool'],

            'upload' => ['bool'],
            'logUpload' => ['bool'],
            'capacity' => ['array'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'schedule' => [],

            // States
            'ipAddress' => '',
            'workingState' => null,
            'error' => null,
            'moveDetected' => false,
            'petDetected' => false,
            'lightning' => false,
            'lastSnapshot' => null,
            'stream' => null,

            // Meta
            'shareOpen' => false,
            'multiConfig' => true,
            'autoUpgrade' => false,
            'typeCode' => 0,
            'serviceStatus' => 2,
            'hertz' => 50,

            // Display / camera
            'timeDisplay' => true,
            'camera' => true,
            'cameraMultiRange' => [
                'name' => 'cameraMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],
            'microphone' => true,
            'night' => true,
            'cameraLight' => true,
            'toiletLight' => true,
            'preLive' => true,

            // Lights
            'lightMode' => true,
            'lightMultiRange' => [
                'name' => 'lightMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],
            'lightAssist' => true,
            'cameraRangeTable' => $this->defaultRangeTable(),

            // Do not disturb
            'toneMode' => false,
            'toneMultiRange' => [
                'name' => 'toneMultiRange',
                'ranges' => [['from' => 1320, 'till' => 360]],
            ],
            'disturbMode' => false,
            'distrubMultiRange' => [
                'name' => 'distrubMultiRange',
                'ranges' => [['from' => 35, 'till' => 515]],
            ],

            // Lock
            'manualLock' => false,
            'clickOkEnable' => true,

            // Cleaning
            'autoWork' => false,
            'autoIntervalMin' => 0,
            'fixedTimeClear' => 0,
            'avoidRepeat' => true,
            'underweight' => false,
            'kitten' => false,
            'bury' => true,
            'deepClean' => true,
            'downpos' => false,
            'sandSaving' => true,
            'tumbling' => false,
            'lightest' => 1520,
            'stillTime' => 120,
            'sandType' => 1,
            'unit' => 0,

            // Toilet / spray
            'toiletDetection' => true,
            'phDetection' => false,
            'softMode' => false,
            'autoSpray' => false,
            'deepSpray' => false,
            'fixedTimeSpray' => 0,
            'sprayDays' => 45,
            'deodorTipEn' => true,

            // Detection
            'moveDetection' => false,
            'moveSensitivity' => 0,
            'petDetection' => true,
            'petSensitivity' => 0,
            'detectInterval' => 0,
            'detectMultiRange' => [],

            // Sound
            'soundEnable' => true,
            'systemSoundEnable' => true,
            'volume' => 7,
            'selectedSound' => -1,
            'voice' => false,

            'upload' => true,
            'logUpload' => true,
            'capacity' => [
                ['name' => 'fullVideo'],
                ['name' => 'eventImage'],
                ['name' => 'highLight'],
                ['name' => 'dynamicVideo'],
            ],
        ];
    }

    protected function casts(): array
    {
        return [
            'schedule' => new ArrayCast(),

            // States
            'ipAddress' => new StringCast(),
            'workingState' => new StringCast(),
            'error' => new StringCast(),
            'moveDetected' => new BooleanCast(),
            'petDetected' => new BooleanCast(),
            'lightning' => new BooleanCast(),
            'lastSnapshot' => new StringCast(),
            'stream' => new StringCast(),

            // Meta
            'shareOpen' => new BooleanCast(),
            'multiConfig' => new BooleanCast(),
            'autoUpgrade' => new BooleanCast(),
            'typeCode' => new IntegerCast(),
            'serviceStatus' => new IntegerCast(),
            'hertz' => new IntegerCast(),

            // Display / camera
            'timeDisplay' => new BooleanCast(),
            'camera' => new BooleanCast(),
            'cameraMultiRange' => new DTOCast(MultiRangeDTO::class),
            'microphone' => new BooleanCast(),
            'night' => new BooleanCast(),
            'cameraLight' => new BooleanCast(),
            'toiletLight' => new BooleanCast(),
            'preLive' => new BooleanCast(),

            // Lights
            'lightMode' => new BooleanCast(),
            'lightMultiRange' => new DTOCast(MultiRangeDTO::class),
            'lightAssist' => new BooleanCast(),
            'cameraRangeTable' => new ArrayCast(),

            // Do not disturb
            'toneMode' => new BooleanCast(),
            'toneMultiRange' => new DTOCast(MultiRangeDTO::class),
            'disturbMode' => new BooleanCast(),
            'distrubMultiRange' => new DTOCast(MultiRangeDTO::class),

            // Lock
            'manualLock' => new BooleanCast(),
            'clickOkEnable' => new BooleanCast(),

            // Cleaning
            'autoWork' => new BooleanCast(),
            'autoIntervalMin' => new IntegerCast(),
            'fixedTimeClear' => new IntegerCast(),
            'avoidRepeat' => new BooleanCast(),
            'underweight' => new BooleanCast(),
            'kitten' => new BooleanCast(),
            'bury' => new BooleanCast(),
            'deepClean' => new BooleanCast(),
            'downpos' => new BooleanCast(),
            'sandSaving' => new BooleanCast(),
            'tumbling' => new BooleanCast(),
            'lightest' => new IntegerCast(),
            'stillTime' => new IntegerCast(),
            'sandType' => new IntegerCast(),
            'unit' => new IntegerCast(),

            // Toilet / spray
            'toiletDetection' => new BooleanCast(),
            'phDetection' => new BooleanCast(),
            'softMode' => new BooleanCast(),
            'autoSpray' => new BooleanCast(),
            'deepSpray' => new BooleanCast(),
            'fixedTimeSpray' => new IntegerCast(),
            'sprayDays' => new IntegerCast(),
            'deodorTipEn' => new BooleanCast(),

            // Detection
            'moveDetection' => new BooleanCast(),
            'moveSensitivity' => new IntegerCast(),
            'petDetection' => new BooleanCast(),
            'petSensitivity' => new IntegerCast(),
            'detectInterval' => new IntegerCast(),
            'detectMultiRange' => new ArrayCast(),

            // Sound
            'soundEnable' => new BooleanCast(),
            'systemSoundEnable' => new BooleanCast(),
            'volume' => new IntegerCast(),
            'selectedSound' => new IntegerCast(),
            'voice' => new BooleanCast(),

            'upload' => new BooleanCast(),
            'logUpload' => new BooleanCast(),
            'capacity' => new ArrayCast(),
        ];
    }

    private function defaultRangeTable(): array
    {
        return array_map(fn (int $wday) => ['wday' => $wday, 'rangeSub' => [0]], range(0, 6));
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
            $data['moveDetected'] = $states['moveDetected'] ?? null;
            $data['petDetected'] = $states['petDetected'] ?? null;
            $data['lightning'] = $states['lightning'] ?? null;
            $data['lastSnapshot'] = $states['lastSnapshot'] ?? null;
            $data['stream'] = $states['stream'] ?? null;
        }

        if (isset($config['settings'])) {
            $settings = $config['settings'];

            // Meta
            $data['shareOpen'] = $settings['shareOpen'] ?? null;
            $data['multiConfig'] = $settings['multiConfig'] ?? null;
            $data['autoUpgrade'] = $settings['autoUpgrade'] ?? null;
            $data['typeCode'] = $settings['typeCode'] ?? null;
            $data['serviceStatus'] = $settings['serviceStatus'] ?? null;
            $data['hertz'] = $settings['hertz'] ?? null;

            // Display / camera
            $data['timeDisplay'] = $settings['timeDisplay'] ?? null;
            $data['camera'] = $settings['camera'] ?? null;
            $data['cameraMultiRange'] = $settings['cameraMultiRange'] ?? null;
            $data['microphone'] = $settings['microphone'] ?? null;
            $data['night'] = $settings['night'] ?? null;
            $data['cameraLight'] = $settings['cameraLight'] ?? null;
            $data['toiletLight'] = $settings['toiletLight'] ?? null;
            $data['preLive'] = $settings['preLive'] ?? null;

            // Lights
            $data['lightMode'] = $settings['lightMode'] ?? null;
            $data['lightMultiRange'] = $settings['lightMultiRange'] ?? null;
            $data['lightAssist'] = $settings['lightAssist'] ?? null;
            $data['cameraRangeTable'] = $settings['cameraRangeTable'] ?? null;

            // Do not disturb
            $data['toneMode'] = $settings['toneMode'] ?? null;
            $data['toneMultiRange'] = $settings['toneMultiRange'] ?? null;
            $data['disturbMode'] = $settings['disturbMode'] ?? null;
            $data['distrubMultiRange'] = $settings['distrubMultiRange'] ?? null;

            // Lock
            $data['manualLock'] = $settings['manualLock'] ?? null;
            $data['clickOkEnable'] = $settings['clickOkEnable'] ?? null;

            // Cleaning
            $data['autoWork'] = $settings['autoWork'] ?? null;
            $data['autoIntervalMin'] = $settings['autoIntervalMin'] ?? null;
            $data['fixedTimeClear'] = $settings['fixedTimeClear'] ?? null;
            $data['avoidRepeat'] = $settings['avoidRepeat'] ?? null;
            $data['underweight'] = $settings['underweight'] ?? null;
            $data['kitten'] = $settings['kitten'] ?? null;
            $data['bury'] = $settings['bury'] ?? null;
            $data['deepClean'] = $settings['deepClean'] ?? null;
            $data['downpos'] = $settings['downpos'] ?? null;
            $data['sandSaving'] = $settings['sandSaving'] ?? null;
            $data['tumbling'] = $settings['tumbling'] ?? null;
            $data['lightest'] = $settings['lightest'] ?? null;
            $data['stillTime'] = $settings['stillTime'] ?? null;
            $data['sandType'] = $settings['sandType'] ?? null;
            $data['unit'] = $settings['unit'] ?? null;

            // Toilet / spray
            $data['toiletDetection'] = $settings['toiletDetection'] ?? null;
            $data['phDetection'] = $settings['phDetection'] ?? null;
            $data['softMode'] = $settings['softMode'] ?? null;
            $data['autoSpray'] = $settings['autoSpray'] ?? null;
            $data['deepSpray'] = $settings['deepSpray'] ?? null;
            $data['fixedTimeSpray'] = $settings['fixedTimeSpray'] ?? null;
            $data['sprayDays'] = $settings['sprayDays'] ?? null;
            $data['deodorTipEn'] = $settings['deodorTipEn'] ?? null;

            // Detection
            $data['moveDetection'] = $settings['moveDetection'] ?? null;
            $data['moveSensitivity'] = $settings['moveSensitivity'] ?? null;
            $data['petDetection'] = $settings['petDetection'] ?? null;
            $data['petSensitivity'] = $settings['petSensitivity'] ?? null;
            $data['detectInterval'] = $settings['detectInterval'] ?? null;
            $data['detectMultiRange'] = $settings['detectMultiRange'] ?? null;

            // Sound
            $data['soundEnable'] = $settings['soundEnable'] ?? null;
            $data['systemSoundEnable'] = $settings['systemSoundEnable'] ?? null;
            $data['volume'] = $settings['volume'] ?? null;
            $data['selectedSound'] = $settings['selectedSound'] ?? null;
            $data['voice'] = $settings['voice'] ?? null;

            $data['upload'] = $settings['upload'] ?? null;
            $data['logUpload'] = $settings['logUpload'] ?? null;
        }

        // Load schedule and capacity
        $data['schedule'] = $config['schedule'] ?? null;
        $data['capacity'] = $config['capacity'] ?? null;

        // detectMultiRange is a legitimate empty array; keep it if present
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
                'lastSnapshot' => $this->lastSnapshot,
                'stream' => $this->stream,
                'moveDetected' => $this->moveDetected,
                'petDetected' => $this->petDetected,
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

                // Display / camera
                'timeDisplay' => $this->timeDisplay,
                'camera' => $this->camera,
                'cameraMultiRange' => $this->cameraMultiRange,
                'microphone' => $this->microphone,
                'night' => $this->night,
                'cameraLight' => $this->cameraLight,
                'toiletLight' => $this->toiletLight,
                'preLive' => $this->preLive,

                // Lights
                'lightMode' => $this->lightMode,
                'lightMultiRange' => $this->lightMultiRange,
                'lightAssist' => $this->lightAssist,
                'cameraRangeTable' => $this->cameraRangeTable,

                // Do not disturb
                'toneMode' => $this->toneMode,
                'toneMultiRange' => $this->toneMultiRange,
                'disturbMode' => $this->disturbMode,
                'distrubMultiRange' => $this->distrubMultiRange,

                // Lock
                'manualLock' => $this->manualLock,
                'clickOkEnable' => $this->clickOkEnable,

                // Cleaning
                'autoWork' => $this->autoWork,
                'autoIntervalMin' => $this->autoIntervalMin,
                'fixedTimeClear' => $this->fixedTimeClear,
                'avoidRepeat' => $this->avoidRepeat,
                'underweight' => $this->underweight,
                'kitten' => $this->kitten,
                'bury' => $this->bury,
                'deepClean' => $this->deepClean,
                'downpos' => $this->downpos,
                'sandSaving' => $this->sandSaving,
                'tumbling' => $this->tumbling,
                'lightest' => $this->lightest,
                'stillTime' => $this->stillTime,
                'sandType' => $this->sandType,
                'unit' => $this->unit,

                // Toilet / spray
                'toiletDetection' => $this->toiletDetection,
                'phDetection' => $this->phDetection,
                'softMode' => $this->softMode,
                'autoSpray' => $this->autoSpray,
                'deepSpray' => $this->deepSpray,
                'fixedTimeSpray' => $this->fixedTimeSpray,
                'sprayDays' => $this->sprayDays,
                'deodorTipEn' => $this->deodorTipEn,

                // Detection
                'moveDetection' => $this->moveDetection,
                'moveSensitivity' => $this->moveSensitivity,
                'petDetection' => $this->petDetection,
                'petSensitivity' => $this->petSensitivity,
                'detectInterval' => $this->detectInterval,
                'detectMultiRange' => $this->detectMultiRange,

                // Sound
                'soundEnable' => $this->soundEnable,
                'systemSoundEnable' => $this->systemSoundEnable,
                'volume' => $this->volume,
                'selectedSound' => $this->selectedSound,
                'voice' => $this->voice,

                'upload' => $this->upload,
                'logUpload' => $this->logUpload,
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
}
