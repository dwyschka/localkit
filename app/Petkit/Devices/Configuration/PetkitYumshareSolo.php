<?php

namespace App\Petkit\Devices\Configuration;

use App\DTOs\DeviceConfigurationDTO;
use App\DTOs\RangeDTO;
use App\Homeassistant\BinarySensor;
use App\Homeassistant\Button;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Image;
use App\Homeassistant\Interfaces\Snapshot;
use App\Homeassistant\Interfaces\Video;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Models\Device;
use Illuminate\Support\Facades\Storage;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

class PetkitYumshareSolo extends DeviceConfigurationDTO implements ConfigurationInterface, Video, Snapshot
{
    // Basic settings
    public int $factor;
    public int $amount;
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
        icon: 'mdi:error',
        valueTemplate: '{{ value_json.states.error }}',
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
        technicalName: 'eat_detected',
        name: 'Eat Detected',
        icon: 'mdi:food',
        valueTemplate: '{{ value_json.states.eatDetected }}',
        entityCategory: 'diagnostic',
        payloadOn: true,
        payloadOff: false
    )]
    public bool $eatDetected;

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

    public bool $lightMode;
    public bool $multiConfig;
    public bool $shareOpen;

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
        technicalName: 'time_display',
        name: 'Time Display',
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

    public bool $eatVideo;

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
        name: 'Pet Detection',
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
        name: 'Pet Sensitivity',
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
        name: 'Eat Detection',
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
        name: 'Eat Sensitivity',
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

    public int $detectInterval;

    // Sound settings
    public bool $toneMode;

    #[HASwitch(
        technicalName: 'sound_enable',
        name: 'Sound Enabled',
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
        name: 'System Sound Enabled',
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
        max: 9,
        step: 1
    )]
    public int $volume;

    public int $selectedSound;

    // Feed amount
    #[Select(
        technicalName: 'amount',
        name: 'Feed Amount',
        options: ['10', '15', '20', '25', '30', '35', '40', '45', '50'],
        commandTopic: 'setting/set',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.settings.amount }}',
        commandTemplate: '{"amount": {{value}}}',
        entityCategory: 'config'
    )]
    // Defined above as public int $amount;

        // AI and other settings
    public int $numLimit;
    public int $surplusControl;
    public int $surplusStandard;

    #[HASwitch(
        technicalName: 'smart_frame',
        name: 'Smart Frame',
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

    public bool $upload;
    public int $serviceStatus;
    public bool $feedPicture;
    public array $attire;
    public bool $autoUpgrade;
    public array $capacity;
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

    #[Button(
        technicalName: 'action_snapshot',
        name: 'Take Snapshot',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "snapshot"}',
        availabilityTemplate: 'online',
    )]
    private $actionSnapshot = 1;

    #[Number(
        technicalName: 'desiccant_durability',
        name: 'N50 Durability',
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
        valueTemplate: '{{ ((value_json.consumables.desiccantNextChange - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    public int $desiccantNextChange;

    protected function rules(): array
    {
        return [
            'factor' => ['integer', 'min:1', 'max:100'],
            'amount' => ['integer', 'in:10,15,20,25,30,35,40,45,50'],
            'schedule' => ['array'],


            'desiccantDurability' => ['integer', 'min:0', 'max:90'],
            'desiccantNextChange' => [ 'integer', 'min:0'],

            // States
            'ipAddress' => ['string'],
            'workingState' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'moveDetected' => ['bool'],
            'eatDetected' => ['bool'],
            'petDetected' => ['bool'],
            'door' => ['bool'],
            'bowl' => ['integer'],
            'infrared' => ['bool'],
            'lastSnapshot' => ['nullable', 'string'],
            'stream' => ['nullable', 'string'],

            // Settings
            'foodWarn' => ['bool'],
            'foodWarnRange' => [],
            'manualLock' => ['bool'],
            'lightMode' => ['bool'],
            'multiConfig' => ['bool'],
            'shareOpen' => ['bool'],

            // Camera
            'camera' => ['bool'],
            'microphone' => ['bool'],
            'night' => ['bool'],
            'timeDisplay' => ['bool'],
            'eatVideo' => ['bool'],

            // Detection
            'moveDetection' => ['bool'],
            'moveSensitivity' => ['integer', 'min:1', 'max:9'],
            'petDetection' => ['bool'],
            'petSensitivity' => ['integer', 'min:1', 'max:9'],
            'eatDetection' => ['bool'],
            'eatSensitivity' => ['integer', 'min:1', 'max:9'],
            'detectInterval' => ['integer', 'min:0'],

            // Sound
            'toneMode' => ['bool'],
            'soundEnable' => ['bool'],
            'systemSoundEnable' => ['bool'],
            'volume' => ['integer', 'min:0', 'max:9'],
            'selectedSound' => ['integer'],

            // AI and other
            'numLimit' => ['integer'],
            'surplusControl' => ['integer'],
            'surplusStandard' => ['integer'],
            'smartFrame' => ['bool'],
            'upload' => ['bool'],
            'serviceStatus' => ['integer'],
            'feedPicture' => ['bool'],
            'attire' => ['array'],
            'autoUpgrade' => ['bool'],
            'capacity' => ['array'],
            'typeCode' => ['integer'],
            'hertz' => ['integer', 'min:50', 'max:60'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'factor' => 10,
            'amount' => 10,
            'schedule' => [],

            'desiccantDurability' => 30,
            'desiccantNextChange' => 0,

            // States
            'ipAddress' => '',
            'workingState' => null,
            'error' => null,
            'moveDetected' => false,
            'eatDetected' => false,
            'petDetected' => false,
            'door' => false,
            'bowl' => -1,
            'infrared' => false,
            'lastSnapshot' => null,
            'stream' => null,

            // Settings
            'foodWarn' => false,
            'foodWarnRange' => ['from' => 480, 'till' => 1200],
            'manualLock' => false,
            'lightMode' => false,
            'multiConfig' => true,
            'shareOpen' => false,

            // Camera
            'camera' => true,
            'microphone' => true,
            'night' => true,
            'timeDisplay' => true,
            'eatVideo' => false,

            // Detection
            'moveDetection' => true,
            'moveSensitivity' => 1,
            'petDetection' => true,
            'petSensitivity' => 3,
            'eatDetection' => true,
            'eatSensitivity' => 3,
            'detectInterval' => 0,

            // Sound
            'toneMode' => false,
            'soundEnable' => false,
            'systemSoundEnable' => false,
            'volume' => 4,
            'selectedSound' => -1,

            // AI and other
            'numLimit' => 5,
            'surplusControl' => 60,
            'surplusStandard' => 2,
            'smartFrame' => true,
            'upload' => false,
            'serviceStatus' => 2,
            'feedPicture' => false,
            'attire' => [
                'id' => -1,
                'binFile' => '',
                'binEncrypt' => '',
                'indate' => 4102415999,
                'binFile2' => '',
                'binEncrypt2' => ''
            ],
            'autoUpgrade' => false,
            'capacity' => [
                ['name' => 'fullVideo'],
                ['name' => 'eventImage'],
                ['name' => 'highLight'],
                ['name' => 'dynamicVideo']
            ],
            'typeCode' => 0,
            'hertz' => 50,
        ];
    }

    protected function casts(): array
    {
        return [
            // Basic types
            'factor' => new IntegerCast(),
            'amount' => new IntegerCast(),
            'schedule' => new ArrayCast(),

            // States
            'ipAddress' => new StringCast(),
            'workingState' => new StringCast(),
            'error' => new StringCast(),
            'moveDetected' => new BooleanCast(),
            'eatDetected' => new BooleanCast(),
            'petDetected' => new BooleanCast(),
            'door' => new BooleanCast(),
            'bowl' => new IntegerCast(),
            'infrared' => new BooleanCast(),
            'lastSnapshot' => new StringCast(),
            'stream' => new StringCast(),

            // Settings
            'foodWarn' => new BooleanCast(),
            'foodWarnRange' => new DTOCast(RangeDTO::class),
            'manualLock' => new BooleanCast(),
            'lightMode' => new BooleanCast(),
            'multiConfig' => new BooleanCast(),
            'shareOpen' => new BooleanCast(),

            // Camera
            'camera' => new BooleanCast(),
            'microphone' => new BooleanCast(),
            'night' => new BooleanCast(),
            'timeDisplay' => new BooleanCast(),
            'eatVideo' => new BooleanCast(),

            // Detection
            'moveDetection' => new BooleanCast(),
            'moveSensitivity' => new IntegerCast(),
            'petDetection' => new BooleanCast(),
            'petSensitivity' => new IntegerCast(),
            'eatDetection' => new BooleanCast(),
            'eatSensitivity' => new IntegerCast(),
            'detectInterval' => new IntegerCast(),

            // Sound
            'toneMode' => new BooleanCast(),
            'soundEnable' => new BooleanCast(),
            'systemSoundEnable' => new BooleanCast(),
            'volume' => new IntegerCast(),
            'selectedSound' => new IntegerCast(),

            // AI and other
            'numLimit' => new IntegerCast(),
            'surplusControl' => new IntegerCast(),
            'surplusStandard' => new IntegerCast(),
            'smartFrame' => new BooleanCast(),
            'upload' => new BooleanCast(),
            'serviceStatus' => new IntegerCast(),
            'feedPicture' => new BooleanCast(),
            'attire' => new ArrayCast(),
            'autoUpgrade' => new BooleanCast(),
            'capacity' => new ArrayCast(),
            'typeCode' => new IntegerCast(),
            'hertz' => new IntegerCast(),
        ];
    }

    public static function fromDevice(Device $device): self
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
            $data['moveDetected'] = $states['moveDetected'] ?? null;
            $data['eatDetected'] = $states['eatDetected'] ?? null;
            $data['petDetected'] = $states['petDetected'] ?? null;
            $data['door'] = $states['door'] ?? null;
            $data['bowl'] = $states['bowl'] ?? null;
            $data['infrared'] = $states['infrared'] ?? null;
            $data['lastSnapshot'] = $states['lastSnapshot'] ?? null;
            $data['stream'] = $states['stream'] ?? null;
        }

        // Load settings
        if (isset($config['settings'])) {
            $settings = $config['settings'];

            $data['shareOpen'] = $settings['shareOpen'] ?? null;
            $data['factor'] = $settings['factor'] ?? null;
            $data['amount'] = $settings['amount'] ?? null;
            $data['multiConfig'] = $settings['multiConfig'] ?? null;
            $data['lightMode'] = $settings['lightMode'] ?? null;
            $data['manualLock'] = $settings['manualLock'] ?? null;
            $data['foodWarnRange'] = $settings['foodWarnRange'] ?? null;
            $data['foodWarn'] = $settings['foodWarn'] ?? null;
            $data['typeCode'] = $settings['typeCode'] ?? null;
            $data['autoUpgrade'] = $settings['autoUpgrade'] ?? null;
            $data['hertz'] = $settings['hertz'] ?? null;

            // Camera settings
            $data['camera'] = $settings['camera'] ?? null;
            $data['microphone'] = $settings['microphone'] ?? null;
            $data['night'] = $settings['night'] ?? null;
            $data['timeDisplay'] = $settings['timeDisplay'] ?? null;
            $data['eatVideo'] = $settings['eatVideo'] ?? null;

            // Detection settings
            $data['moveDetection'] = $settings['moveDetection'] ?? null;
            $data['moveSensitivity'] = $settings['moveSensitivity'] ?? null;
            $data['petDetection'] = $settings['petDetection'] ?? null;
            $data['petSensitivity'] = $settings['petSensitivity'] ?? null;
            $data['eatDetection'] = $settings['eatDetection'] ?? null;
            $data['eatSensitivity'] = $settings['eatSensitivity'] ?? null;
            $data['detectInterval'] = $settings['detectInterval'] ?? null;

            // Sound settings
            $data['toneMode'] = $settings['toneMode'] ?? null;
            $data['soundEnable'] = $settings['soundEnable'] ?? null;
            $data['systemSoundEnable'] = $settings['systemSoundEnable'] ?? null;
            $data['volume'] = $settings['volume'] ?? null;
            $data['selectedSound'] = $settings['selectedSound'] ?? null;

            // AI and other settings
            $data['numLimit'] = $settings['numLimit'] ?? null;
            $data['surplusControl'] = $settings['surplusControl'] ?? null;
            $data['surplusStandard'] = $settings['surplusStandard'] ?? null;
            $data['smartFrame'] = $settings['smartFrame'] ?? null;
            $data['upload'] = $settings['upload'] ?? null;
            $data['attire'] = $settings['attire'] ?? null;
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
                'moveDetected' => $this->moveDetected,
                'eatDetected' => $this->eatDetected,
                'petDetected' => $this->petDetected,
                'infrared' => $this->infrared,
            ],
            'settings' => [
                'shareOpen' => $this->shareOpen,
                'factor' => $this->factor,
                'amount' => $this->amount,
                'multiConfig' => $this->multiConfig,
                'lightMode' => $this->lightMode,
                'manualLock' => $this->manualLock,
                'foodWarnRange' => $this->foodWarnRange->toArray(),
                'foodWarn' => $this->foodWarn,
                'typeCode' => $this->typeCode,
                'autoUpgrade' => $this->autoUpgrade,
                'hertz' => $this->hertz,

                // Camera settings
                'camera' => $this->camera,
                'microphone' => $this->microphone,
                'night' => $this->night,
                'timeDisplay' => $this->timeDisplay,
                'eatVideo' => $this->eatVideo,

                // Detection settings
                'moveDetection' => $this->moveDetection,
                'moveSensitivity' => $this->moveSensitivity,
                'petDetection' => $this->petDetection,
                'petSensitivity' => $this->petSensitivity,
                'eatDetection' => $this->eatDetection,
                'eatSensitivity' => $this->eatSensitivity,
                'detectInterval' => $this->detectInterval,

                // Sound settings
                'toneMode' => $this->toneMode,
                'soundEnable' => $this->soundEnable,
                'systemSoundEnable' => $this->systemSoundEnable,
                'volume' => $this->volume,
                'selectedSound' => $this->selectedSound,

                // AI and other settings
                'numLimit' => $this->numLimit,
                'surplusControl' => $this->surplusControl,
                'surplusStandard' => $this->surplusStandard,
                'smartFrame' => $this->smartFrame,
                'upload' => $this->upload,
                'attire' => $this->attire,
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
}
