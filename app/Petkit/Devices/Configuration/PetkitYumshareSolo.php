<?php

namespace App\Petkit\Devices\Configuration;

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

class PetkitYumshareSolo implements ConfigurationInterface, Video, Snapshot
{

    #[Sensor(
        technicalName: 'ip_address',
        name: 'IP Address',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.ipAddress }}',
        entityCategory: 'diagnostic'
    )]
    private string $ipAddress = '';
    private array $schedule = [];

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

    #[HASwitch(
        technicalName: 'food_warn',
        name: 'Refill alarm',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.foodWarn }}',
        commandTemplate: '{"foodWarn":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private int $foodWarn = 0;
    private array $foodWarnRange = [480, 1200];

    #[Sensor(
        technicalName: 'device_status',
        name: 'Device Status',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.state }}',
        entityCategory: 'diagnostic'
    )]
    private ?string $workingState = null;

    #[Sensor(
        technicalName: 'error',
        name: 'Error',
        icon: 'mdi:error',
        valueTemplate: '{{ value_json.states.error }}',
        entityCategory: 'diagnostic'
    )]
    private ?string $error = null;

    #[HASwitch(
        technicalName: 'manual_lock',
        name: 'Child lock',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.manualLock | string }}',
        commandTemplate: '{"manualLock":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private int $manualLock = 0;
    private int $lightMode = 0;

    private int $factor = 10;

    #[Select(
        technicalName: 'amount',
        name: 'Feed Amount',
        options: [
            '10',
            '15',
            '20',
            '25',
            '30',
            '35',
            '40',
            '45',
            '50'
        ],
        commandTopic: 'setting/set',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.settings.amount }}',
        commandTemplate: ' {"amount": {{value}}}',
        entityCategory: 'config'
    )]
    private int $amount = 10;

    #[HASwitch(
        technicalName: 'camera',
        name: 'Camera',
        commandTopic: 'setting/set',
        icon: 'mdi:camera',
        valueTemplate: '{{ value_json.settings.camera }}',
        commandTemplate: '{"camera":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private bool $camera = true;

    #[HASwitch(
        technicalName: 'microphone',
        name: 'Microphone',
        commandTopic: 'setting/set',
        icon: 'mdi:microphone',
        valueTemplate: '{{ value_json.settings.microphone }}',
        commandTemplate: '{"microphone":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private bool $microphone = true;

    #[HASwitch(
        technicalName: 'night',
        name: 'Night Vision',
        commandTopic: 'setting/set',
        icon: 'mdi:moon-new',
        valueTemplate: '{{ value_json.settings.night }}',
        commandTemplate: '{"night":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private bool $night = true;

    #[HASwitch(
        technicalName: 'time_display',
        name: 'Time Display',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.timeDisplay }}',
        commandTemplate: '{"timeDisplay":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]

    private bool $timeDisplay = true;
    private bool $eatVideo = true;

    #[HASwitch(
        technicalName: 'move_detection',
        name: 'Move Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:eye-arrow-left-outline',
        valueTemplate: '{{ value_json.settings.moveDetection }}',
        commandTemplate: '{"moveDetection":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private bool $moveDetection = true;

    #[Number(
        technicalName: 'move_sensitivity',
        name: 'Move Sensitivity',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.moveSensitivity }}',
        commandTemplate: '{"moveSensitivity":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config',
        min: 1,
        max: 9,
        step: 1
    )]
    private int $moveSensitivity = 1;

    #[HASwitch(
        technicalName: 'pet_detection',
        name: 'Pet Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:cat',
        valueTemplate: '{{ value_json.settings.petDetection }}',
        commandTemplate: '{"petDetection":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private bool $petDetection = true;

    #[Number(
        technicalName: 'pet_sensitivity',
        name: 'Pet Sensitivity',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.petSensitivity }}',
        commandTemplate: '{"petSensitivity":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config',
        min: 1,
        max: 9,
        step: 1
    )]
    private int $petSensitivity = 3;

    #[HASwitch(
        technicalName: 'eat_detection',
        name: 'Eat Detection',
        commandTopic: 'setting/set',
        icon: 'mdi:bowl',
        valueTemplate: '{{ value_json.settings.eatDetection }}',
        commandTemplate: '{"eatDetection":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private bool $eatDetection = true;

    #[Number(
        technicalName: 'eat_sensitivity',
        name: 'Eat Sensitivity',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.eatSensitivity }}',
        commandTemplate: '{"eatSensitivity":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config',
        min: 1,
        max: 9,
        step: 1
    )]
    private int $eatSensitivity = 3;
    private int $detectInterval = 0;
    private int $toneMode = 1;

    #[HASwitch(
        technicalName: 'sound_enable',
        name: 'Sound Enabled',
        commandTopic: 'setting/set',
        icon: 'mdi:volume-low',
        valueTemplate: '{{ value_json.settings.soundEnable }}',
        commandTemplate: '{"soundEnable":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private bool $soundEnable = false;

    #[HASwitch(
        technicalName: 'system_sound_enable',
        name: 'System Sound Enabled',
        commandTopic: 'setting/set',
        icon: 'mdi:desktop-classic',
        valueTemplate: '{{ value_json.settings.systemSoundEnable }}',
        commandTemplate: '{"systemSoundEnable":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private bool $systemSoundEnable = false;

    #[Number(
        technicalName: 'volume',
        name: 'Volume',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.volume }}',
        commandTemplate: '{"volume":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config',
        min: 1,
        max: 9,
        step: 1
    )]
    private int $volume = 4;    //Volume is 0 - 9


    private int $selectedSound = -1;    // -1 for system-sound */


    private int $numLimit = 5; //dunno
    private int $surplusControl = 60; //Some ai stuff ?
    private int $surplusStandard = 2; // Ai Stuff

    #[HASwitch(
        technicalName: 'smart_frame',
        name: 'Smart Frame',
        commandTopic: 'setting/set',
        icon: 'mdi:border',
        valueTemplate: '{{ value_json.settings.smartFrame }}',
        commandTemplate: '{"smartFrame":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private bool $smartFrame = true; //renders border in stream
    private bool $upload = true;

    private int $serviceStatus = 2;
    private bool $feedPicture = false;

    //No fcking idea what this is
    private array $attire = [
        'id' => -1,
        'binFile' => '',
        'binEncrypt' => '',
        'indate' => 4102415999,
        'binFile2' => '',
        'binEncrypt2' => ''
    ];

    private bool $multiConfig = true;
    private bool $shareOpen = false;
    private int $typeCode = 0;
    private bool $autoUpgrade = false;
    private array $capacity = [[
        'name' => 'fullVideo'
    ], [
        'name' => 'eventImage'
    ], [
        'name' => 'highLight'
    ], [
        'name' => 'dynamicVideo'
    ]];


    #[Sensor(
        technicalName: 'hertz',
        name: 'Hertz',
        icon: 'mdi:repeat',
        valueTemplate: '{{ value_json.settings.hertz }}',
        entityCategory: 'diagnostic'
    )]
    private int $hertz = 50;

    #[Image(
        technicalName: 'last_snapshot',
        name: 'Snapshot',
    )]
    private ?string $lastSnapshot = null;

    #[BinarySensor(
        technicalName: 'move_detected',
        name: 'Move Detected',
        icon: 'mdi:cursor-move',
        deviceClass: 'motion',
        valueTemplate: '{{ value_json.states.moveDetected }}',
        entityCategory: 'diagnostic',
        payloadOn: 'True',
        payloadOff: 'False'
    )]
    private bool $moveDetected = false;

    #[BinarySensor(
        technicalName: 'eat_detected',
        name: 'Eat Detected',
        icon: 'mdi:food',
        valueTemplate: '{{ value_json.states.eatDetected }}',
        entityCategory: 'diagnostic',
        payloadOn: 'True',
        payloadOff: 'False'
    )]
    private bool $eatDetected = false;

    #[BinarySensor(
        technicalName: 'pet_detected',
        name: 'Pet Detected',
        icon: 'mdi:cat',
        deviceClass: 'motion',
        valueTemplate: '{{ value_json.states.petDetected }}',
        entityCategory: 'diagnostic',
        payloadOn: 'True',
        payloadOff: 'False'
    )]
    private bool $petDetected = false;

    #[BinarySensor(
        technicalName: 'door',
        name: 'Door',
        icon: 'mdi:door',
        valueTemplate: '{{ value_json.states.door }}',
        entityCategory: 'diagnostic',
        payloadOn: 'True',
        payloadOff: 'False'
    )]
    private bool $door = false;

    #[Sensor(
        technicalName: 'bowl',
        name: 'Bowl',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.bowl }}',
        entityCategory: 'diagnostic'
    )]
    private int $bowl = -1;

    #[BinarySensor(
        technicalName: 'infrared',
        name: 'Infrared',
        valueTemplate: '{{ value_json.states.infrared ? "on": "off" }}',
        payloadOn: 'True',
        payloadOff: 'False'
    )]
    private bool $infrared = false;
    private ?string $stream = null;

    /**
     * Constructor that initializes the configuration from a Device object
     */
    public function __construct(private ?Device $device)
    {
        if (!is_null($device)) {
            $this->loadFromDevice();
        }
    }

    /**
     * Load configuration from the device
     */
    private function loadFromDevice(): void
    {
        // States
        $this->error = $this->device->error;
        $this->workingState = $this->device->working_state;

        $config = $this->device->configuration;
        // Load schedule
        $this->schedule = $config['schedule'] ?? [];

        if (isset($config['states'])) {
            $states = $config['states'];
            $this->lastSnapshot = $states['lastSnapshot'] ?? $this->lastSnapshot;
            $this->ipAddress = $states['ipAddress'] ?? $this->ipAddress;
            $this->moveDetected = $states['moveDetected'] ?? $this->moveDetected;
            $this->eatDetected = $states['eatDetected'] ?? $this->eatDetected;
            $this->petDetected = $states['petDetected'] ?? $this->petDetected;
            $this->door = $states['door'] ?? $this->door;
            $this->bowl = $states['bowl'] ?? $this->bowl;
            $this->infrared = $states['infrared'] ?? $this->infrared;
            $this->stream = $states['stream'] ?? $this->stream;
        }

        // Load settings
        if (isset($config['settings'])) {
            $settings = $config['settings'];

            $this->shareOpen = $settings['shareOpen'] ?? $this->shareOpen;
            $this->factor = $settings['factor'] ?? $this->factor;
            $this->amount = $settings['amount'] ?? $this->amount;

            $this->multiConfig = $settings['multiConfig'] ?? $this->multiConfig;
            $this->lightMode = $settings['lightMode'] ?? $this->lightMode;
            $this->manualLock = $settings['manualLock'] ?? $this->manualLock;
            $this->foodWarnRange = $settings['foodWarnRange'] ?? $this->foodWarnRange;
            $this->foodWarn = $settings['foodWarn'] ?? $this->foodWarn;
            $this->typeCode = $settings['typeCode'] ?? $this->typeCode;
            $this->autoUpgrade = $settings['autoUpgrade'] ?? $this->autoUpgrade;
            $this->hertz = $settings['hertz'] ?? $this->hertz;

            // Camera and detection settings
            $this->camera = $settings['camera'] ?? $this->camera;
            $this->microphone = $settings['microphone'] ?? $this->microphone;
            $this->night = $settings['night'] ?? $this->night;
            $this->timeDisplay = $settings['timeDisplay'] ?? $this->timeDisplay;
            $this->eatVideo = $settings['eatVideo'] ?? $this->eatVideo;
            $this->moveDetection = $settings['moveDetection'] ?? $this->moveDetection;
            $this->moveSensitivity = $settings['moveSensitivity'] ?? $this->moveSensitivity;
            $this->petDetection = $settings['petDetection'] ?? $this->petDetection;
            $this->petSensitivity = $settings['petSensitivity'] ?? $this->petSensitivity;
            $this->eatDetection = $settings['eatDetection'] ?? $this->eatDetection;
            $this->eatSensitivity = $settings['eatSensitivity'] ?? $this->eatSensitivity;
            $this->detectInterval = $settings['detectInterval'] ?? $this->detectInterval;

            // Sound settings
            $this->toneMode = $settings['toneMode'] ?? $this->toneMode;
            $this->soundEnable = $settings['soundEnable'] ?? $this->soundEnable;
            $this->systemSoundEnable = $settings['systemSoundEnable'] ?? $this->systemSoundEnable;
            $this->volume = $settings['volume'] ?? $this->volume;
            $this->selectedSound = $settings['selectedSound'] ?? $this->selectedSound;

            // AI and upload settings
            $this->numLimit = $settings['numLimit'] ?? $this->numLimit;
            $this->surplusControl = $settings['surplusControl'] ?? $this->surplusControl;
            $this->surplusStandard = $settings['surplusStandard'] ?? $this->surplusStandard;
            $this->smartFrame = $settings['smartFrame'] ?? $this->smartFrame;
            $this->upload = $settings['upload'] ?? $this->upload;

            // Attire settings
            $this->attire = $settings['attire'] ?? $this->attire;

            // Capacity settings
            $this->capacity = $settings['capacity'] ?? $this->capacity;
            $this->feedPicture = $settings['feedPicture'] ?? $this->feedPicture;
            $this->serviceStatus = $settings['serviceStatus'] ?? $this->serviceStatus;

        }
    }

    public function toSnapshot(): ?string
    {
        if (is_null($this->lastSnapshot)) {
            return $this->lastSnapshot;
        }
        return base64_encode(Storage::disk('snapshots')->get($this->lastSnapshot));
    }

    /**
     * Convert the configuration to an array
     */
    public function toArray(): array
    {
        return [
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
                'foodWarnRange' => $this->foodWarnRange,
                'foodWarn' => $this->foodWarn,
                'typeCode' => $this->typeCode,
                'autoUpgrade' => $this->autoUpgrade,
                'hertz' => $this->hertz,

                // Camera and detection settings
                'camera' => $this->camera,
                'microphone' => $this->microphone,
                'night' => $this->night,
                'timeDisplay' => $this->timeDisplay,
                'eatVideo' => $this->eatVideo,
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

                // AI and upload settings
                'numLimit' => $this->numLimit,
                'surplusControl' => $this->surplusControl,
                'surplusStandard' => $this->surplusStandard,
                'smartFrame' => $this->smartFrame,
                'upload' => $this->upload,

                // Attire and capacity settings
                'attire' => $this->attire,
                'feedPicture' => $this->feedPicture,
                'serviceStatus' => $this->serviceStatus,
            ],
            'schedule' => $this->schedule,
            'capacity' => $this->capacity,
        ];
    }

    public function getDevice()
    {
        return $this->device;
    }

    public function getCamera(): bool
    {
        return $this->camera;
    }

    public function setCamera(bool $camera): void
    {
        $this->camera = $camera;
    }

    public function getMicrophone(): bool
    {
        return $this->microphone;
    }

    public function setMicrophone(bool $microphone): void
    {
        $this->microphone = $microphone;
    }

    public function getNight(): bool
    {
        return $this->night;
    }

    public function setNight(bool $night): void
    {
        $this->night = $night;
    }

    public function getTimeDisplay(): bool
    {
        return $this->timeDisplay;
    }

    public function setTimeDisplay(bool $timeDisplay): void
    {
        $this->timeDisplay = $timeDisplay;
    }

    public function getEatVideo(): bool
    {
        return $this->eatVideo;
    }

    public function setEatVideo(bool $eatVideo): void
    {
        $this->eatVideo = $eatVideo;
    }

    public function getMoveDetection(): bool
    {
        return $this->moveDetection;
    }

    public function setMoveDetection(bool $moveDetection): void
    {
        $this->moveDetection = $moveDetection;
    }

    public function getMoveSensitivity(): int
    {
        return $this->moveSensitivity;
    }

    public function setMoveSensitivity(int $moveSensitivity): void
    {
        $this->moveSensitivity = $moveSensitivity;
    }

    public function getPetDetection(): bool
    {
        return $this->petDetection;
    }

    public function setPetDetection(bool $petDetection): void
    {
        $this->petDetection = $petDetection;
    }

    public function getPetSensitivity(): int
    {
        return $this->petSensitivity;
    }

    public function setPetSensitivity(int $petSensitivity): void
    {
        $this->petSensitivity = $petSensitivity;
    }

    public function getUpload(): bool
    {
        return $this->upload;
    }

    public function setUpload(bool $upload): void
    {
        $this->upload = $upload;
    }

    public function getSmartFrame(): bool
    {
        return $this->smartFrame;
    }

    public function setSmartFrame(bool $smartFrame): void
    {
        $this->smartFrame = $smartFrame;
    }

    public function getSelectedSound(): int
    {
        return $this->selectedSound;
    }

    public function setSelectedSound(int $selectedSound): void
    {
        $this->selectedSound = $selectedSound;
    }

    public function getCapacity(): array
    {
        return $this->capacity;
    }

    public function getTypeCode(): int
    {
        return $this->typeCode;
    }

    public function getEatDetection(): bool
    {
        return $this->eatDetection;
    }

    public function setEatDetection(bool $eatDetection): void
    {
        $this->eatDetection = $eatDetection;
    }

    public function getEatSensitivity(): int
    {
        return $this->eatSensitivity;
    }

    public function setEatSensitivity(int $eatSensitivity): void
    {
        $this->eatSensitivity = $eatSensitivity;
    }

    public function getDetectInterval(): int
    {
        return $this->detectInterval;
    }

    public function setDetectInterval(int $detectInterval): void
    {
        $this->detectInterval = $detectInterval;
    }

    public function getToneMode(): int
    {
        return $this->toneMode;
    }

    public function setToneMode(int $toneMode): void
    {
        $this->toneMode = $toneMode;
    }

    public function getAutoUpgrade(): bool
    {
        return $this->autoUpgrade;
    }

    public function setAutoUpgrade(bool $autoUpgrade): void
    {
        $this->autoUpgrade = $autoUpgrade;
    }

    public function getSurplusStandard(): int
    {
        return $this->surplusStandard;
    }

    public function setSurplusStandard(int $surplusStandard): void
    {
        $this->surplusStandard = $surplusStandard;
    }

    public function getVolume(): int
    {
        return $this->volume;
    }

    public function setVolume(int $volume): void
    {
        $this->volume = $volume;
    }

    public function getHertz(): int
    {
        return $this->hertz;
    }

    public function setHertz(int $hertz): void
    {
        $this->hertz = $hertz;
    }

    public function getSystemSoundEnable(): bool
    {
        return $this->systemSoundEnable;
    }

    public function setSystemSoundEnable(bool $systemSoundEnable): void
    {
        $this->systemSoundEnable = $systemSoundEnable;
    }

    public function getAttire(): array
    {
        return $this->attire;
    }

    public function getSoundEnable(): bool
    {
        return $this->soundEnable;
    }

    public function setSoundEnable(bool $soundEnable): void
    {
        $this->soundEnable = $soundEnable;
    }

    public function getNumLimit(): int
    {
        return $this->numLimit;
    }

    public function getSurplusControl(): int
    {
        return $this->surplusControl;
    }

    public function setSurplusControl(int $surplusControl): void
    {
        $this->surplusControl = $surplusControl;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getLastSnapshot(): ?string
    {
        return $this->lastSnapshot;
    }

    public function setLastSnapshot(?string $lastSnapshot): void
    {
        $this->lastSnapshot = $lastSnapshot;
    }

    public function getFeedPicture(): bool
    {
        return $this->feedPicture;
    }

    public function setFeedPicture(bool $feedPicture): void
    {
        $this->feedPicture = $feedPicture;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }


}
