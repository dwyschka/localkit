<?php

namespace App\Petkit\Devices\YumshareDual;

use App\DTOs\DeviceConfigurationDTO;
use App\DTOs\RangeDTO;
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
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\Interfaces\HasCamera;
use Illuminate\Support\Facades\Storage;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

/**
 * Configuration for the PetKit YumShare Dual (d4sh) - a dual-hopper feeder.
 *
 * NOTE: the property names must match the device's setting keys exactly, since
 * {@see \App\Petkit\Devices\YumshareDual\Device::propertyChange()} diffs the
 * stored `settings` array and forwards the changed keys straight to the device.
 * The real device uses a mix of snake_case (e.g. sche_enable) and
 * camelCase (e.g. moveDetection) setting keys - both are kept verbatim below.
 */
class Configuration extends DeviceConfigurationDTO implements ConfigurationInterface, Video, Snapshot, HasCamera
{
    // Basic settings
    #[Number(
        technicalName: 'amount1',
        name: 'Feed Amount Hopper 1',
        commandTopic: 'setting/set',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.settings.amount1 }}',
        commandTemplate: '{"amount1": {{value}}}',
        entityCategory: 'config',
        min: 0,
        max: 50,
        step: 1
    )]
    public int $amount1;

    #[Number(
        technicalName: 'amount2',
        name: 'Feed Amount Hopper 2',
        commandTopic: 'setting/set',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.settings.amount2 }}',
        commandTemplate: '{"amount2": {{value}}}',
        entityCategory: 'config',
        min: 0,
        max: 50,
        step: 1
    )]
    public int $amount2;
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
        technicalName: 'door',
        name: 'Door',
        icon: 'mdi:door',
        valueTemplate: '{{ value_json.states.door }}',
        entityCategory: 'diagnostic',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $door;

    #[Sensor(
        technicalName: 'bowl',
        name: 'Bowl',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.bowl }}',
        entityCategory: 'diagnostic'
    )]
    public int $bowl;

    #[Image(
        technicalName: 'last_snapshot',
        name: 'Snapshot',
    )]
    public ?string $lastSnapshot;

    public ?string $stream;

    // Switches
    #[HASwitch(
        technicalName: 'food_warn',
        name: 'Refill alarm',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.foodWarn }}',
        commandTemplate: '{"foodWarn":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $foodWarn;

    public RangeDTO $foodWarnRange;

    #[HASwitch(
        technicalName: 'manual_lock',
        name: 'Child lock',
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

    public array $lightMultiRange;

    #[HASwitch(
        technicalName: 'multi_config',
        name: 'Multi Config',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.multiConfig }}',
        commandTemplate: '{"multiConfig":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $multiConfig;

    #[HASwitch(
        technicalName: 'share_open',
        name: 'Share Open',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.shareOpen }}',
        commandTemplate: '{"shareOpen":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $shareOpen;

    // Schedule
    #[HASwitch(
        technicalName: 'sche_enable',
        name: 'Feeding Schedule',
        commandTopic: 'setting/set',
        icon: 'mdi:calendar-clock',
        valueTemplate: '{{ value_json.settings.sche_enable }}',
        commandTemplate: '{"sche_enable":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $sche_enable;

    #[Sensor(
        technicalName: 'c_time',
        name: 'Schedule Change Time',
        icon: 'mdi:clock-outline',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.CTime }}'
    )]
    public int $CTime;

    // Camera settings
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
        technicalName: 'timeDisplay',
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

    #[BinarySensor(
        technicalName: 'eat_video',
        name: 'YUMSHARE Video/Photo Upload',
        icon: 'mdi:cloud-upload',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.eatVideo }}',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $eatVideo;

    // Hopper calibration factors (bowl 1 / bowl 2)
    #[Number(
        technicalName: 'factor1',
        name: 'Hopper 1 Calibration Factor',
        commandTopic: 'setting/set',
        icon: 'mdi:tune-variant',
        valueTemplate: '{{ value_json.settings.factor1 }}',
        commandTemplate: '{"factor1":{{ value }}}',
        entityCategory: 'config',
        min: 1,
        max: 100,
        step: 1
    )]
    public int $factor1;

    #[Number(
        technicalName: 'factor2',
        name: 'Hopper 2 Calibration Factor',
        commandTopic: 'setting/set',
        icon: 'mdi:tune-variant',
        valueTemplate: '{{ value_json.settings.factor2 }}',
        commandTemplate: '{"factor2":{{ value }}}',
        entityCategory: 'config',
        min: 1,
        max: 100,
        step: 1
    )]
    public int $factor2;

    // Detection settings
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
        min: 1,
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

    #[Number(
        technicalName: 'pet_sensitivity',
        name: 'Pet Visit Sensitivity',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.petSensitivity }}',
        commandTemplate: '{"petSensitivity":{{ value }}}',
        entityCategory: 'config',
        min: 1,
        max: 9,
        step: 1
    )]
    public int $petSensitivity;

    #[HASwitch(
        technicalName: 'eat_detection',
        name: 'Pet Eat Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:bowl',
        valueTemplate: '{{ value_json.settings.eatDetection }}',
        commandTemplate: '{"eatDetection":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $eatDetection;

    #[Number(
        technicalName: 'eat_sensitivity',
        name: 'Pet Eat Sensitivity',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.eatSensitivity }}',
        commandTemplate: '{"eatSensitivity":{{ value }}}',
        entityCategory: 'config',
        min: 1,
        max: 9,
        step: 1
    )]
    public int $eatSensitivity;

    #[Number(
        technicalName: 'detect_interval',
        name: 'Detection Interval',
        commandTopic: 'setting/set',
        icon: 'mdi:timer-outline',
        unitOfMeasurement: 's',
        valueTemplate: '{{ value_json.settings.detectInterval }}',
        commandTemplate: '{"detectInterval":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 300,
        step: 1
    )]
    public int $detectInterval;

    public array $detectMultiRange;

    // Sound settings
    #[HASwitch(
        technicalName: 'tone_mode',
        name: 'Do Not Disturb',
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

    public array $toneMultiRange;

    #[HASwitch(
        technicalName: 'sound_enable',
        name: 'Voice for Food Dispensing',
        commandTopic: 'setting/set',
        icon: 'mdi:volume-low',
        valueTemplate: '{{ value_json.settings.soundEnable }}',
        commandTemplate: '{"soundEnable":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $soundEnable;

    #[HASwitch(
        technicalName: 'system_sound_enable',
        name: 'Voice Prompt',
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

    #[HASwitch(
        technicalName: 'feed_sound',
        name: 'Feed Completion Sound',
        commandTopic: 'setting/set',
        icon: 'mdi:volume-high',
        valueTemplate: '{{ value_json.settings.feedSound }}',
        commandTemplate: '{"feedSound":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $feedSound;

    #[Number(
        technicalName: 'volume',
        name: 'Volume',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.volume }}',
        commandTemplate: '{"volume":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 9,
        step: 1
    )]
    public int $volume;

    #[Sensor(
        technicalName: 'selected_sound',
        name: 'Selected Sound',
        icon: 'mdi:music-note',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.selectedSound }}'
    )]
    public int $selectedSound;

    // AI and other settings
    #[Sensor(
        technicalName: 'surplus_control',
        name: 'Surplus Food Control',
        icon: 'mdi:food-off',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.surplusControl }}'
    )]
    public int $surplusControl;

    #[Number(
        technicalName: 'surplus_standard',
        name: 'Surplus Food Standard',
        commandTopic: 'setting/set',
        icon: 'mdi:food-off',
        valueTemplate: '{{ value_json.settings.surplusStandard }}',
        commandTemplate: '{"surplusStandard":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 100,
        step: 1
    )]
    public int $surplusStandard;

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

    public bool $vomitDetection;

    #[HASwitch(
        technicalName: 'upload',
        name: 'Cloud Recording',
        commandTopic: 'setting/set',
        icon: 'mdi:cloud-upload',
        valueTemplate: '{{ value_json.settings.upload }}',
        commandTemplate: '{"upload":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $upload;

    #[Sensor(
        technicalName: 'service_status',
        name: 'Service Status',
        icon: 'mdi:information-outline',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.serviceStatus }}'
    )]
    public int $serviceStatus;

    #[HASwitch(
        technicalName: 'feed_picture',
        name: 'Feeding Photo',
        commandTopic: 'setting/set',
        icon: 'mdi:camera',
        valueTemplate: '{{ value_json.settings.feedPicture }}',
        commandTemplate: '{"feedPicture":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $feedPicture;

    #[Sensor(
        technicalName: 'attire_id',
        name: 'Attire ID',
        icon: 'mdi:identifier',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.attireId }}'
    )]
    public int $attireId;

    #[Sensor(
        technicalName: 'logo_cn',
        name: 'Logo CN',
        icon: 'mdi:identifier',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.logo_cn }}'
    )]
    public int $logo_cn;

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

    public array $capacity;

    #[Sensor(
        technicalName: 'type_code',
        name: 'Type Code',
        icon: 'mdi:identifier',
        entityCategory: 'diagnostic',
        valueTemplate: '{{ value_json.settings.typeCode }}'
    )]
    public int $typeCode;

    #[Sensor(
        technicalName: 'hertz',
        name: 'Hertz',
        icon: 'mdi:repeat',
        valueTemplate: '{{ value_json.settings.hertz }}',
        entityCategory: 'diagnostic'
    )]
    public int $hertz;

    // Buttons
    #[Button(
        technicalName: 'action_feed',
        name: 'Feed',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "feed"}',
        availabilityTemplate: 'online',
    )]
    private $actionFeed = 1;

    #[Number(
        technicalName: 'desiccant_durability',
        name: 'Desiccant Durability',
        commandTopic: 'setting/set',
        icon: 'mdi:diamond-stone',
        valueTemplate: '{{ value_json.consumables.desiccantDurability }}',
        commandTemplate: '{"desiccantDurability":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    public int $desiccantDurability;


    #[Sensor(
        technicalName: 'durability_in_days',
        name: 'Next Desiccant Change in Days',
        icon: 'mdi:update',
        unitOfMeasurement: 'd',
        valueTemplate: '{{ ((value_json.consumables.desiccantNextChange - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    public int $desiccantNextChange;

    protected function rules(): array
    {
        return [
            'amount1' => ['integer', 'min:0', 'max:50'],
            'amount2' => ['integer', 'min:0', 'max:50'],
            'schedule' => ['array'],

            'desiccantDurability' => ['integer', 'min:0', 'max:90'],
            'desiccantNextChange' => ['integer', 'min:0'],

            // States
            'ipAddress' => ['string'],
            'workingState' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'door' => ['bool'],
            'bowl' => ['integer'],
            'lastSnapshot' => ['nullable', 'string'],
            'stream' => ['nullable', 'string'],

            // Settings
            'foodWarn' => ['bool'],
            'foodWarnRange' => [],
            'manualLock' => ['bool'],
            'lightMode' => ['bool'],
            'lightMultiRange' => ['array'],
            'multiConfig' => ['bool'],
            'shareOpen' => ['bool'],

            // Schedule
            'sche_enable' => ['bool'],
            'CTime' => ['integer'],

            // Camera
            'camera' => ['bool'],
            'cameraMultiRange' => ['array'],
            'cameraRangeTable' => ['array'],
            'microphone' => ['bool'],
            'night' => ['bool'],
            'timeDisplay' => ['bool'],
            'eatVideo' => ['bool'],

            // Hopper factors
            'factor1' => ['integer', 'min:1', 'max:100'],
            'factor2' => ['integer', 'min:1', 'max:100'],

            // Detection
            'moveDetection' => ['bool'],
            'moveSensitivity' => ['integer', 'min:1', 'max:9'],
            'petDetection' => ['bool'],
            'petSensitivity' => ['integer', 'min:1', 'max:9'],
            'eatDetection' => ['bool'],
            'eatSensitivity' => ['integer', 'min:1', 'max:9'],
            'detectInterval' => ['integer', 'min:0'],
            'detectMultiRange' => ['array'],

            // Sound
            'toneMode' => ['bool'],
            'toneMultiRange' => ['array'],
            'soundEnable' => ['bool'],
            'systemSoundEnable' => ['bool'],
            'feedSound' => ['bool'],
            'volume' => ['integer', 'min:0', 'max:9'],
            'selectedSound' => ['integer'],

            // AI and other
            'surplusControl' => ['integer'],
            'surplusStandard' => ['integer'],
            'smartFrame' => ['bool'],
            'vomitDetection' => ['bool'],
            'upload' => ['bool'],
            'serviceStatus' => ['integer'],
            'feedPicture' => ['bool'],
            'attireId' => ['integer'],
            'logo_cn' => ['integer'],
            'autoUpgrade' => ['bool'],
            'capacity' => ['array'],
            'typeCode' => ['integer'],
            'hertz' => ['integer', 'min:50', 'max:60'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'amount1' => 1,
            'amount2' => 1,
            'schedule' => [],

            'desiccantDurability' => 30,
            'desiccantNextChange' => 0,

            // States
            'ipAddress' => '',
            'workingState' => null,
            'error' => null,
            'door' => false,
            'bowl' => -1,
            'lastSnapshot' => null,
            'stream' => null,

            // Settings
            'foodWarn' => false,
            'foodWarnRange' => ['from' => 480, 'till' => 1200],
            'manualLock' => false,
            'lightMode' => true,
            'lightMultiRange' => [[0, 1440]],
            'multiConfig' => true,
            'shareOpen' => false,

            // Schedule
            'sche_enable' => false,
            'CTime' => 0,

            // Camera
            'camera' => true,
            'cameraMultiRange' => [[0, 1440]],
            'cameraRangeTable' => $this->defaultRangeTable(),
            'microphone' => true,
            'night' => true,
            'timeDisplay' => true,
            'eatVideo' => true,

            // Hopper factors
            'factor1' => 1,
            'factor2' => 1,

            // Detection
            'moveDetection' => false,
            'moveSensitivity' => 1,
            'petDetection' => true,
            'petSensitivity' => 3,
            'eatDetection' => true,
            'eatSensitivity' => 3,
            'detectInterval' => 0,
            'detectMultiRange' => [[0, 1440]],

            // Sound
            'toneMode' => false,
            'toneMultiRange' => [[1320, 360]],
            'soundEnable' => false,
            'systemSoundEnable' => true,
            'feedSound' => true,
            'volume' => 3,
            'selectedSound' => -1,

            // AI and other
            'surplusControl' => 0,
            'surplusStandard' => 2,
            'smartFrame' => true,
            'vomitDetection' => false,
            'upload' => true,
            'serviceStatus' => 2,
            'feedPicture' => true,
            'attireId' => -1,
            'logo_cn' => 0,
            'autoUpgrade' => false,
            'capacity' => [
                ['name' => 'fullVideo', 'workTime' => 0, 'indate' => 0],
                ['name' => 'eventImage', 'workTime' => 0, 'indate' => 0],
                ['name' => 'highLight', 'workTime' => 0, 'indate' => 0],
                ['name' => 'dynamicVideo', 'workTime' => 0, 'indate' => 0],
            ],
            'typeCode' => 0,
            'hertz' => 50,
        ];
    }

    protected function casts(): array
    {
        return [
            'amount1' => new IntegerCast(),
            'amount2' => new IntegerCast(),
            'schedule' => new ArrayCast(),

            // States
            'ipAddress' => new StringCast(),
            'workingState' => new StringCast(),
            'error' => new StringCast(),
            'door' => new BooleanCast(),
            'bowl' => new IntegerCast(),
            'lastSnapshot' => new StringCast(),
            'stream' => new StringCast(),

            // Settings
            'foodWarn' => new BooleanCast(),
            'foodWarnRange' => new DTOCast(RangeDTO::class),
            'manualLock' => new BooleanCast(),
            'lightMode' => new BooleanCast(),
            'lightMultiRange' => new ArrayCast(),
            'multiConfig' => new BooleanCast(),
            'shareOpen' => new BooleanCast(),

            // Schedule
            'sche_enable' => new BooleanCast(),
            'CTime' => new IntegerCast(),

            // Camera
            'camera' => new BooleanCast(),
            'cameraMultiRange' => new ArrayCast(),
            'cameraRangeTable' => new ArrayCast(),
            'microphone' => new BooleanCast(),
            'night' => new BooleanCast(),
            'timeDisplay' => new BooleanCast(),
            'eatVideo' => new BooleanCast(),

            // Hopper factors
            'factor1' => new IntegerCast(),
            'factor2' => new IntegerCast(),

            // Detection
            'moveDetection' => new BooleanCast(),
            'moveSensitivity' => new IntegerCast(),
            'petDetection' => new BooleanCast(),
            'petSensitivity' => new IntegerCast(),
            'eatDetection' => new BooleanCast(),
            'eatSensitivity' => new IntegerCast(),
            'detectInterval' => new IntegerCast(),
            'detectMultiRange' => new ArrayCast(),

            // Sound
            'toneMode' => new BooleanCast(),
            'toneMultiRange' => new ArrayCast(),
            'soundEnable' => new BooleanCast(),
            'systemSoundEnable' => new BooleanCast(),
            'feedSound' => new BooleanCast(),
            'volume' => new IntegerCast(),
            'selectedSound' => new IntegerCast(),

            // AI and other
            'surplusControl' => new IntegerCast(),
            'surplusStandard' => new IntegerCast(),
            'smartFrame' => new BooleanCast(),
            'vomitDetection' => new BooleanCast(),
            'upload' => new BooleanCast(),
            'serviceStatus' => new IntegerCast(),
            'feedPicture' => new BooleanCast(),
            'attireId' => new IntegerCast(),
            'logo_cn' => new IntegerCast(),
            'autoUpgrade' => new BooleanCast(),
            'capacity' => new ArrayCast(),
            'typeCode' => new IntegerCast(),
            'hertz' => new IntegerCast(),
        ];
    }

    public static function fromDevice(Device|BluetoothDevice $device): self
    {
        $config = $device->configuration;
        $data = [];
        // Load consumables
        $data['desiccantDurability'] = $config['consumables']['desiccantDurability'] ?? null;
        $data['desiccantNextChange'] = $config['consumables']['desiccantNextChange'] ?? null;

        // Load states
        $data['workingState'] = $device->working_state ?? null;
        $data['error'] = $device->error ?? null;

        if (isset($config['states'])) {
            $states = $config['states'];
            $data['ipAddress'] = $states['ipAddress'] ?? null;
            $data['door'] = $states['door'] ?? null;
            $data['bowl'] = $states['bowl'] ?? null;
            $data['lastSnapshot'] = $states['lastSnapshot'] ?? null;
            $data['stream'] = $states['stream'] ?? null;
        }

        // Load settings
        if (isset($config['settings'])) {
            $settings = $config['settings'];

            $data['shareOpen'] = $settings['shareOpen'] ?? null;
            $data['amount1'] = $settings['amount1'] ?? null;
            $data['amount2'] = $settings['amount2'] ?? null;
            $data['multiConfig'] = $settings['multiConfig'] ?? null;
            $data['lightMode'] = $settings['lightMode'] ?? null;
            $data['lightMultiRange'] = $settings['lightMultiRange'] ?? null;
            $data['manualLock'] = $settings['manualLock'] ?? null;
            $data['foodWarnRange'] = $settings['foodWarnRange'] ?? null;
            $data['foodWarn'] = $settings['foodWarn'] ?? null;
            $data['typeCode'] = $settings['typeCode'] ?? null;
            $data['autoUpgrade'] = $settings['autoUpgrade'] ?? null;
            $data['hertz'] = $settings['hertz'] ?? null;

            // Schedule
            $data['sche_enable'] = $settings['sche_enable'] ?? null;
            $data['CTime'] = $settings['CTime'] ?? null;

            // Camera settings
            $data['camera'] = $settings['camera'] ?? null;
            $data['cameraMultiRange'] = $settings['cameraMultiRange'] ?? null;
            $data['cameraRangeTable'] = $settings['cameraRangeTable'] ?? null;
            $data['microphone'] = $settings['microphone'] ?? null;
            $data['night'] = $settings['night'] ?? null;
            $data['timeDisplay'] = $settings['timeDisplay'] ?? null;
            $data['eatVideo'] = $settings['eatVideo'] ?? null;

            // Hopper factors
            $data['factor1'] = $settings['factor1'] ?? null;
            $data['factor2'] = $settings['factor2'] ?? null;

            // Detection settings
            $data['moveDetection'] = $settings['moveDetection'] ?? null;
            $data['moveSensitivity'] = $settings['moveSensitivity'] ?? null;
            $data['petDetection'] = $settings['petDetection'] ?? null;
            $data['petSensitivity'] = $settings['petSensitivity'] ?? null;
            $data['eatDetection'] = $settings['eatDetection'] ?? null;
            $data['eatSensitivity'] = $settings['eatSensitivity'] ?? null;
            $data['detectInterval'] = $settings['detectInterval'] ?? null;
            $data['detectMultiRange'] = $settings['detectMultiRange'] ?? null;

            // Sound settings
            $data['toneMode'] = $settings['toneMode'] ?? null;
            $data['toneMultiRange'] = $settings['toneMultiRange'] ?? null;
            $data['soundEnable'] = $settings['soundEnable'] ?? null;
            $data['systemSoundEnable'] = $settings['systemSoundEnable'] ?? null;
            $data['feedSound'] = $settings['feedSound'] ?? null;
            $data['volume'] = $settings['volume'] ?? null;
            $data['selectedSound'] = $settings['selectedSound'] ?? null;

            // AI and other settings
            $data['surplusControl'] = $settings['surplusControl'] ?? null;
            $data['surplusStandard'] = $settings['surplusStandard'] ?? null;
            $data['smartFrame'] = $settings['smartFrame'] ?? null;
            $data['vomitDetection'] = $settings['vomitDetection'] ?? null;
            $data['upload'] = $settings['upload'] ?? null;
            $data['attireId'] = $settings['attireId'] ?? null;
            $data['logo_cn'] = $settings['logo_cn'] ?? null;
            $data['feedPicture'] = $settings['feedPicture'] ?? null;
            $data['serviceStatus'] = $settings['serviceStatus'] ?? null;
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
                'desiccantDurability' => $this->desiccantDurability,
                'desiccantNextChange' => $this->desiccantNextChange,
            ],
            'states' => [
                'state' => $this->workingState,
                'error' => $this->error,
                'ipAddress' => $this->ipAddress,
                'door' => $this->door,
                'bowl' => $this->bowl,
                'lastSnapshot' => $this->lastSnapshot,
                'stream' => $this->stream,
            ],
            'settings' => [
                'shareOpen' => $this->shareOpen,
                'amount1' => $this->amount1,
                'amount2' => $this->amount2,
                'multiConfig' => $this->multiConfig,
                'lightMode' => $this->lightMode,
                'lightMultiRange' => $this->lightMultiRange,
                'manualLock' => $this->manualLock,
                'foodWarnRange' => $this->foodWarnRange->toArray(),
                'foodWarn' => $this->foodWarn,
                'typeCode' => $this->typeCode,
                'autoUpgrade' => $this->autoUpgrade,
                'hertz' => $this->hertz,

                // Schedule
                'sche_enable' => $this->sche_enable,
                'CTime' => $this->CTime,

                // Camera settings
                'camera' => $this->camera,
                'cameraMultiRange' => $this->cameraMultiRange,
                'cameraRangeTable' => $this->cameraRangeTable,
                'microphone' => $this->microphone,
                'night' => $this->night,
                'timeDisplay' => $this->timeDisplay,
                'eatVideo' => $this->eatVideo,

                // Hopper factors
                'factor1' => $this->factor1,
                'factor2' => $this->factor2,

                // Detection settings
                'moveDetection' => $this->moveDetection,
                'moveSensitivity' => $this->moveSensitivity,
                'petDetection' => $this->petDetection,
                'petSensitivity' => $this->petSensitivity,
                'eatDetection' => $this->eatDetection,
                'eatSensitivity' => $this->eatSensitivity,
                'detectInterval' => $this->detectInterval,
                'detectMultiRange' => $this->detectMultiRange,

                // Sound settings
                'toneMode' => $this->toneMode,
                'toneMultiRange' => $this->toneMultiRange,
                'soundEnable' => $this->soundEnable,
                'systemSoundEnable' => $this->systemSoundEnable,
                'feedSound' => $this->feedSound,
                'volume' => $this->volume,
                'selectedSound' => $this->selectedSound,

                // AI and other settings
                'surplusControl' => $this->surplusControl,
                'surplusStandard' => $this->surplusStandard,
                'smartFrame' => $this->smartFrame,
                'vomitDetection' => $this->vomitDetection,
                'upload' => $this->upload,
                'attireId' => $this->attireId,
                'logo_cn' => $this->logo_cn,
                'feedPicture' => $this->feedPicture,
                'serviceStatus' => $this->serviceStatus,
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

    private function defaultRangeTable(): array
    {
        return array_map(fn (int $wday) => ['wday' => $wday, 'rangeSub' => [0]], range(0, 6));
    }
}
