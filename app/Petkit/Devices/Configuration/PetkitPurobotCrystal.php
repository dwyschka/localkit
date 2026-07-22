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
use App\Homeassistant\Select;
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
 * Configuration for the PetKit PuroBot Crystal (T7) - a camera equipped
 * self-cleaning litter box using crystal litter with a spray/deodorant unit.
 *
 * NOTE: the property names must match the device's setting keys exactly, since
 * {@see \App\Petkit\Devices\PetkitPurobotCrystal::propertyChange()} diffs the
 * stored `settings` array and forwards the changed keys straight to the device.
 */
class PetkitPurobotCrystal extends DeviceConfigurationDTO implements ConfigurationInterface, Video, Snapshot, HasCamera
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
        payloadOn: true,
        payloadOff: false
    )]
    public bool $moveDetected;

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
        technicalName: 'infrared',
        name: 'Infrared',
        valueTemplate: '{{ value_json.states.infrared ? "on": "off" }}',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $infrared;

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
        technicalName: 'microlight',
        name: 'Microphone Light',
        commandTopic: 'setting/set',
        icon: 'mdi:led-on',
        valueTemplate: '{{ value_json.settings.microlight }}',
        commandTemplate: '{"microlight":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $microlight;

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

    public bool $preLive;

    // Indicator / assist lights
    #[HASwitch(
        technicalName: 'light_mode',
        name: 'Indicator Light',
        commandTopic: 'setting/set',
        icon: 'mdi:lightbulb',
        valueTemplate: '{{ value_json.settings.lightMode }}',
        commandTemplate: '{"lightMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $lightMode;

    public MultiRangeDTO $lightMultiRange;

    #[HASwitch(
        technicalName: 'light_assist',
        name: 'Ambient Light Assist',
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

    public MultiRangeDTO $lightAssistMultiRange;

    #[HASwitch(
        technicalName: 'toilet_light_assist',
        name: 'Toilet Light Assist',
        commandTopic: 'setting/set',
        icon: 'mdi:lightbulb-auto',
        valueTemplate: '{{ value_json.settings.toiletLightAssist }}',
        commandTemplate: '{"toiletLightAssist":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toiletLightAssist;

    public MultiRangeDTO $toiletLightAssistMultiRange;

    #[HASwitch(
        technicalName: 'wifi_light_assist',
        name: 'WiFi Light Assist',
        commandTopic: 'setting/set',
        icon: 'mdi:wifi',
        valueTemplate: '{{ value_json.settings.wifiLightAssist }}',
        commandTemplate: '{"wifiLightAssist":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $wifiLightAssist;

    public MultiRangeDTO $wifiLightAssistMultiRange;

    public array $cameraRangeTable;
    public array $lightRangeTable;
    public array $toiletLightRangeTable;

    // Do not disturb / tone
    #[HASwitch(
        technicalName: 'tone_mode',
        name: 'Do not disturb (Tone)',
        commandTopic: 'setting/set',
        icon: 'mdi:volume-off',
        valueTemplate: '{{ value_json.settings.toneMode }}',
        commandTemplate: '{"toneMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $toneMode;

    public MultiRangeDTO $toneMultiRange;

    #[HASwitch(
        technicalName: 'disturb_mode',
        name: 'Do not disturb',
        commandTopic: 'setting/set',
        icon: 'mdi:bell-off',
        valueTemplate: '{{ value_json.settings.disturbMode }}',
        commandTemplate: '{"disturbMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $disturbMode;

    public MultiRangeDTO $distrubMultiRange;

    // Lock
    #[HASwitch(
        technicalName: 'manual_lock',
        name: 'Child lock',
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

    #[HASwitch(
        technicalName: 'underweight',
        name: 'Disable auto cleaning for light weight',
        commandTopic: 'setting/set',
        icon: 'mdi:feather',
        valueTemplate: '{{ value_json.settings.underweight }}',
        commandTemplate: '{"underweight":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
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
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $kitten;

    #[HASwitch(
        technicalName: 'bury',
        name: 'Waste Covering',
        commandTopic: 'setting/set',
        icon: 'mdi:shovel',
        valueTemplate: '{{ value_json.settings.bury }}',
        commandTemplate: '{"bury":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
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
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $deepClean;

    #[HASwitch(
        technicalName: 'downpos',
        name: 'Uninterrupted Rotation',
        commandTopic: 'setting/set',
        icon: 'mdi:cached',
        valueTemplate: '{{ value_json.settings.downpos }}',
        commandTemplate: '{"downpos":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $downpos;

    public bool $tumbling;
    public int $lightest;
    public int $stillTime;

    #[Select(
        technicalName: 'sand_type',
        name: 'Litter Type',
        options: [
            'Sand',
            'Betonit/Mineral',
            'Tofu',
        ],
        commandTopic: 'setting/set',
        icon: 'mdi:information-outline',
        valueTemplate: '
                {% if value_json.settings.sandType == 1 %}
                  Betonit/Mineral
                {% elif value_json.settings.sandType == 2 %}
                  Tofu
                {% else %}
                  Sand
                {% endif %}
        ',
        commandTemplate: '
                {% if value == "Betonit/Mineral" %}
                  {"sandType": 1}
                {% elif value == "Tofu" %}
                  {"sandType": 2}
                {% else %}
                  {"sandType": 0}
                {% endif %}
        ',
        entityCategory: 'config'
    )]
    public int $sandType;

    // No weight sensor on this device - not exposed to HA
    public int $unit;

    #[Number(
        technicalName: 'sand_tray_standard_day',
        name: 'Litter Replacement Cycle',
        commandTopic: 'setting/set',
        icon: 'mdi:calendar-refresh',
        unitOfMeasurement: 'd',
        valueTemplate: '{{ value_json.settings.sandTrayStandardDay }}',
        commandTemplate: '{"sandTrayStandardDay":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    public int $sandTrayStandardDay;

    public int $sandTrayStandardDayMax;

    // Desiccant (deodorant crystal)
    #[Number(
        technicalName: 'n60_durability',
        name: 'N60 Durability',
        commandTopic: 'setting/set',
        icon: 'mdi:diamond-stone',
        valueTemplate: '{{ value_json.consumables.n60Durability }}',
        commandTemplate: '{"n60Durability":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
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

    // Cardboard (litter tray liner)
    #[Number(
        technicalName: 'cardboard_durability',
        name: 'Cardboard Durability',
        commandTopic: 'setting/set',
        icon: 'mdi:package-variant-closed',
        valueTemplate: '{{ value_json.consumables.cardboardDurability }}',
        commandTemplate: '{"cardboardDurability":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    public int $cardboardDurability;

    #[Sensor(
        technicalName: 'cardboard_durability_in_days',
        name: 'Next Cardboard Change in Days',
        icon: 'mdi:update',
        unitOfMeasurement: 'd',
        valueTemplate: '{{ ((value_json.consumables.cardboardNextChange - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    public mixed $cardboardNextChange;

    #[Button(
        technicalName: 'action_reset_cardboard',
        name: 'Reset Cardboard',
        commandTopic: 'action/start',
        icon: 'mdi:package-variant-closed',
        commandTemplate: '{"action": "reset_cardboard"}',
        availabilityTemplate: 'online',
    )]
    private $actionResetCardboard = 1;

    // Toilet / spray (deodorant) settings
    #[HASwitch(
        technicalName: 'toilet_detection',
        name: 'Toilet Detection',
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
        technicalName: 'urine',
        name: 'Urine Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:water',
        valueTemplate: '{{ value_json.settings.urine }}',
        commandTemplate: '{"urine":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $urine;

    #[HASwitch(
        technicalName: 'soft_mode',
        name: 'Soft Mode',
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

    public bool $softModeClean;

    #[HASwitch(
        technicalName: 'occult',
        name: 'Health Monitoring (Occult Blood)',
        commandTopic: 'setting/set',
        icon: 'mdi:heart-pulse',
        valueTemplate: '{{ value_json.settings.occult }}',
        commandTemplate: '{"occult":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $occult;

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
        name: 'Pet Visit Detection',
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

    public int $detectInterval;
    public array $detectMultiRange;

    // Sound (no speaker on this device - not exposed to HA)
    public bool $soundEnable;
    public bool $systemSoundEnable;
    public int $volume;
    public int $selectedSound;
    public bool $voice;

    // Cloud upload - not exposed to HA
    public bool $upload;

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
            'infrared' => ['bool'],
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
            'microlight' => ['bool'],
            'cameraLight' => ['bool'],
            'preLive' => ['bool'],

            // Lights
            'lightMode' => ['bool'],
            'lightMultiRange' => ['array'],
            'lightAssist' => ['bool'],
            'lightAssistMultiRange' => ['array'],
            'toiletLightAssist' => ['bool'],
            'toiletLightAssistMultiRange' => ['array'],
            'wifiLightAssist' => ['bool'],
            'wifiLightAssistMultiRange' => ['array'],
            'cameraRangeTable' => ['array'],
            'lightRangeTable' => ['array'],
            'toiletLightRangeTable' => ['array'],

            // Do not disturb
            'toneMode' => ['bool'],
            'toneMultiRange' => ['array'],
            'disturbMode' => ['bool'],
            'distrubMultiRange' => ['array'],

            // Lock
            'manualLock' => ['bool'],

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
            'tumbling' => ['bool'],
            'lightest' => ['integer', 'min:0'],
            'stillTime' => ['integer', 'min:0'],
            'sandType' => ['integer', 'in:0,1,2'],
            'unit' => ['integer', 'in:0,1'],
            'sandTrayStandardDay' => ['integer', 'min:0', 'max:90'],
            'sandTrayStandardDayMax' => ['integer', 'min:0'],
            'n60Durability' => ['integer', 'min:0', 'max:90'],
            'n60NextChange' => ['nullable'],
            'cardboardDurability' => ['integer', 'min:0', 'max:90'],
            'cardboardNextChange' => ['nullable'],

            // Toilet
            'toiletDetection' => ['bool'],
            'urine' => ['bool'],
            'softMode' => ['bool'],
            'softModeClean' => ['bool'],
            'occult' => ['bool'],

            // Detection
            'moveDetection' => ['bool'],
            'moveSensitivity' => ['integer', 'min:0', 'max:9'],
            'petDetection' => ['bool'],
            'detectInterval' => ['integer', 'min:0'],
            'detectMultiRange' => ['array'],

            // Sound
            'soundEnable' => ['bool'],
            'systemSoundEnable' => ['bool'],
            'volume' => ['integer', 'min:0', 'max:9'],
            'selectedSound' => ['integer'],
            'voice' => ['bool'],

            'upload' => ['bool'],
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
            'infrared' => false,
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
            'microlight' => true,
            'cameraLight' => true,
            'preLive' => true,

            // Lights
            'lightMode' => true,
            'lightMultiRange' => [
                'name' => 'lightMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],
            'lightAssist' => true,
            'lightAssistMultiRange' => [
                'name' => 'lightAssistMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],
            'toiletLightAssist' => true,
            'toiletLightAssistMultiRange' => [
                'name' => 'toiletLightAssistMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],
            'wifiLightAssist' => true,
            'wifiLightAssistMultiRange' => [
                'name' => 'wifiLightAssistMultiRange',
                'ranges' => [['from' => 0, 'till' => 1440]],
            ],
            'cameraRangeTable' => $this->defaultRangeTable(),
            'lightRangeTable' => $this->defaultRangeTable(),
            'toiletLightRangeTable' => $this->defaultRangeTable(),

            // Do not disturb
            'toneMode' => false,
            'toneMultiRange' => [
                'name' => 'toneMultiRange',
                'ranges' => [['from' => 1320, 'till' => 360]],
            ],
            'disturbMode' => false,
            'distrubMultiRange' => [
                'name' => 'distrubMultiRange',
                'ranges' => [['from' => 40, 'till' => 520]],
            ],

            // Lock
            'manualLock' => false,

            // Cleaning
            'autoWork' => true,
            'autoIntervalMin' => 0,
            'fixedTimeClear' => 0,
            'avoidRepeat' => true,
            'underweight' => false,
            'kitten' => false,
            'bury' => false,
            'deepClean' => false,
            'downpos' => false,
            'tumbling' => false,
            'lightest' => 0,
            'stillTime' => 1200,
            'sandType' => 0,
            'unit' => 0,
            'sandTrayStandardDay' => 45,
            'sandTrayStandardDayMax' => 90,
            'n60Durability' => 45,
            'n60NextChange' => 0,
            'cardboardDurability' => 45,
            'cardboardNextChange' => 0,

            // Toilet
            'toiletDetection' => true,
            'urine' => true,
            'softMode' => false,
            'softModeClean' => false,
            'occult' => false,

            // Detection
            'moveDetection' => false,
            'moveSensitivity' => 0,
            'petDetection' => true,
            'detectInterval' => 0,
            'detectMultiRange' => [],

            // Sound
            'soundEnable' => true,
            'systemSoundEnable' => true,
            'volume' => 7,
            'selectedSound' => -1,
            'voice' => false,

            'upload' => true,
            'capacity' => [
                ['name' => 'fullVideo'],
                ['name' => 'eventImage'],
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
            'infrared' => new BooleanCast(),
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
            'time' => new BooleanCast(),
            'camera' => new BooleanCast(),
            'cameraMultiRange' => new DTOCast(MultiRangeDTO::class),
            'microphone' => new BooleanCast(),
            'night' => new BooleanCast(),
            'microlight' => new BooleanCast(),
            'cameraLight' => new BooleanCast(),
            'preLive' => new BooleanCast(),

            // Lights
            'lightMode' => new BooleanCast(),
            'lightMultiRange' => new DTOCast(MultiRangeDTO::class),
            'lightAssist' => new BooleanCast(),
            'lightAssistMultiRange' => new DTOCast(MultiRangeDTO::class),
            'toiletLightAssist' => new BooleanCast(),
            'toiletLightAssistMultiRange' => new DTOCast(MultiRangeDTO::class),
            'wifiLightAssist' => new BooleanCast(),
            'wifiLightAssistMultiRange' => new DTOCast(MultiRangeDTO::class),
            'cameraRangeTable' => new ArrayCast(),
            'lightRangeTable' => new ArrayCast(),
            'toiletLightRangeTable' => new ArrayCast(),

            // Do not disturb
            'toneMode' => new BooleanCast(),
            'toneMultiRange' => new DTOCast(MultiRangeDTO::class),
            'disturbMode' => new BooleanCast(),
            'distrubMultiRange' => new DTOCast(MultiRangeDTO::class),

            // Lock
            'manualLock' => new BooleanCast(),

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
            'tumbling' => new BooleanCast(),
            'lightest' => new IntegerCast(),
            'stillTime' => new IntegerCast(),
            'sandType' => new IntegerCast(),
            'unit' => new IntegerCast(),
            'sandTrayStandardDay' => new IntegerCast(),
            'sandTrayStandardDayMax' => new IntegerCast(),
            'n60Durability' => new IntegerCast(),
            'cardboardDurability' => new IntegerCast(),

            // Toilet
            'toiletDetection' => new BooleanCast(),
            'urine' => new BooleanCast(),
            'softMode' => new BooleanCast(),
            'softModeClean' => new BooleanCast(),
            'occult' => new BooleanCast(),

            // Detection
            'moveDetection' => new BooleanCast(),
            'moveSensitivity' => new IntegerCast(),
            'petDetection' => new BooleanCast(),
            'detectInterval' => new IntegerCast(),
            'detectMultiRange' => new ArrayCast(),

            // Sound
            'soundEnable' => new BooleanCast(),
            'systemSoundEnable' => new BooleanCast(),
            'volume' => new IntegerCast(),
            'selectedSound' => new IntegerCast(),
            'voice' => new BooleanCast(),

            'upload' => new BooleanCast(),
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

        // Load consumables
        $data['n60Durability'] = $config['consumables']['n60Durability'] ?? null;
        $data['n60NextChange'] = $config['consumables']['n60NextChange'] ?? null;
        $data['cardboardDurability'] = $config['consumables']['cardboardDurability'] ?? null;
        $data['cardboardNextChange'] = $config['consumables']['cardboardNextChange'] ?? null;

        // Load states
        $data['workingState'] = $device->working_state ?? null;
        $data['error'] = $device->error ?? null;

        if (isset($config['states'])) {
            $states = $config['states'];
            $data['ipAddress'] = $states['ipAddress'] ?? null;
            $data['moveDetected'] = $states['moveDetected'] ?? null;
            $data['petDetected'] = $states['petDetected'] ?? null;
            $data['infrared'] = $states['infrared'] ?? null;
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
            $data['microlight'] = $settings['microlight'] ?? null;
            $data['cameraLight'] = $settings['cameraLight'] ?? null;
            $data['preLive'] = $settings['preLive'] ?? null;

            // Lights
            $data['lightMode'] = $settings['lightMode'] ?? null;
            $data['lightMultiRange'] = $settings['lightMultiRange'] ?? null;
            $data['lightAssist'] = $settings['lightAssist'] ?? null;
            $data['lightAssistMultiRange'] = $settings['lightAssistMultiRange'] ?? null;
            $data['toiletLightAssist'] = $settings['toiletLightAssist'] ?? null;
            $data['toiletLightAssistMultiRange'] = $settings['toiletLightAssistMultiRange'] ?? null;
            $data['wifiLightAssist'] = $settings['wifiLightAssist'] ?? null;
            $data['wifiLightAssistMultiRange'] = $settings['wifiLightAssistMultiRange'] ?? null;
            $data['cameraRangeTable'] = $settings['cameraRangeTable'] ?? null;
            $data['lightRangeTable'] = $settings['lightRangeTable'] ?? null;
            $data['toiletLightRangeTable'] = $settings['toiletLightRangeTable'] ?? null;

            // Do not disturb
            $data['toneMode'] = $settings['toneMode'] ?? null;
            $data['toneMultiRange'] = $settings['toneMultiRange'] ?? null;
            $data['disturbMode'] = $settings['disturbMode'] ?? null;
            $data['distrubMultiRange'] = $settings['distrubMultiRange'] ?? null;

            // Lock
            $data['manualLock'] = $settings['manualLock'] ?? null;

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
            $data['tumbling'] = $settings['tumbling'] ?? null;
            $data['lightest'] = $settings['lightest'] ?? null;
            $data['stillTime'] = $settings['stillTime'] ?? null;
            $data['sandType'] = $settings['sandType'] ?? null;
            $data['unit'] = $settings['unit'] ?? null;
            $data['sandTrayStandardDay'] = $settings['sandTrayStandardDay'] ?? null;
            $data['sandTrayStandardDayMax'] = $settings['sandTrayStandardDayMax'] ?? null;

            // Toilet
            $data['toiletDetection'] = $settings['toiletDetection'] ?? null;
            $data['urine'] = $settings['urine'] ?? null;
            $data['softMode'] = $settings['softMode'] ?? null;
            $data['softModeClean'] = $settings['softModeClean'] ?? null;
            $data['occult'] = $settings['occult'] ?? null;

            // Detection
            $data['moveDetection'] = $settings['moveDetection'] ?? null;
            $data['moveSensitivity'] = $settings['moveSensitivity'] ?? null;
            $data['petDetection'] = $settings['petDetection'] ?? null;
            $data['detectInterval'] = $settings['detectInterval'] ?? null;
            $data['detectMultiRange'] = $settings['detectMultiRange'] ?? null;

            // Sound
            $data['soundEnable'] = $settings['soundEnable'] ?? null;
            $data['systemSoundEnable'] = $settings['systemSoundEnable'] ?? null;
            $data['volume'] = $settings['volume'] ?? null;
            $data['selectedSound'] = $settings['selectedSound'] ?? null;
            $data['voice'] = $settings['voice'] ?? null;

            $data['upload'] = $settings['upload'] ?? null;
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
            'consumables' => [
                'n60Durability' => $this->n60Durability,
                'n60NextChange' => $this->n60NextChange,
                'cardboardDurability' => $this->cardboardDurability,
                'cardboardNextChange' => $this->cardboardNextChange,
            ],
            'states' => [
                'state' => $this->workingState,
                'error' => $this->error,
                'ipAddress' => $this->ipAddress,
                'lastSnapshot' => $this->lastSnapshot,
                'stream' => $this->stream,
                'moveDetected' => $this->moveDetected,
                'petDetected' => $this->petDetected,
                'infrared' => $this->infrared,
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
                'microlight' => $this->microlight,
                'cameraLight' => $this->cameraLight,
                'preLive' => $this->preLive,

                // Lights
                'lightMode' => $this->lightMode,
                'lightMultiRange' => $this->lightMultiRange,
                'lightAssist' => $this->lightAssist,
                'lightAssistMultiRange' => $this->lightAssistMultiRange,
                'toiletLightAssist' => $this->toiletLightAssist,
                'toiletLightAssistMultiRange' => $this->toiletLightAssistMultiRange,
                'wifiLightAssist' => $this->wifiLightAssist,
                'wifiLightAssistMultiRange' => $this->wifiLightAssistMultiRange,
                'cameraRangeTable' => $this->cameraRangeTable,
                'lightRangeTable' => $this->lightRangeTable,
                'toiletLightRangeTable' => $this->toiletLightRangeTable,

                // Do not disturb
                'toneMode' => $this->toneMode,
                'toneMultiRange' => $this->toneMultiRange,
                'disturbMode' => $this->disturbMode,
                'distrubMultiRange' => $this->distrubMultiRange,

                // Lock
                'manualLock' => $this->manualLock,

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
                'tumbling' => $this->tumbling,
                'lightest' => $this->lightest,
                'stillTime' => $this->stillTime,
                'sandType' => $this->sandType,
                'unit' => $this->unit,
                'sandTrayStandardDay' => $this->sandTrayStandardDay,
                'sandTrayStandardDayMax' => $this->sandTrayStandardDayMax,

                // Toilet
                'toiletDetection' => $this->toiletDetection,
                'urine' => $this->urine,
                'softMode' => $this->softMode,
                'softModeClean' => $this->softModeClean,
                'occult' => $this->occult,

                // Detection
                'moveDetection' => $this->moveDetection,
                'moveSensitivity' => $this->moveSensitivity,
                'petDetection' => $this->petDetection,
                'detectInterval' => $this->detectInterval,
                'detectMultiRange' => $this->detectMultiRange,

                // Sound
                'soundEnable' => $this->soundEnable,
                'systemSoundEnable' => $this->systemSoundEnable,
                'volume' => $this->volume,
                'selectedSound' => $this->selectedSound,
                'voice' => $this->voice,

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
}
