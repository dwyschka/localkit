<?php

namespace App\Petkit\Devices\Configuration;

use App\Helpers\HomeassistantHelper;
use App\Homeassistant\BinarySensor;
use App\Homeassistant\Button;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Models\Device;

class PetkitYumshareSolo implements ConfigurationInterface
{
    private string $ipAddress = '';
    private array $schedule = [];
    private int $foodWarn = 0;
    private array $foodWarnRange = [480, 1200];
    private int $manualLock = 0;
    private int $lightMode = 0;
    private int $factor = 10;
    private bool $camera = true;
    private bool $microphone = true;
    private bool $night = true;
    private bool $timeDisplay = true;
    private bool $eatVideo = true;
    private bool $moveDetection = true;
    private int $moveSensitivity = 1;
    private bool $petDetection = true;

    private int $petSensitivity = 3;
    private bool $eatDetection = true;
    private int $eatSensitivity = 3;
    private int $detectInterval = 0;
    private int $toneMode = 1;
    private bool $soundEnable = false;
    private bool $systemSoundEnable = false;

    private int $volume = 4;    //Volume is 0 - 9


    private int $selectedSound = -1;    // -1 for system-sound */


    private int $numLimit = 5; //dunno
    private int $surplusControl = 60; //Some ai stuff ?
    private int $surplusStandard = 2; // Ai Stuff

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

    private int $hertz = 50;
    private ?string $lastSnapshot = null;
    private bool $moveDetected = false;
    private bool $eatDetected = false;
    private bool $petDetected = false;

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

        if(isset($config['states'])) {
            $states = $config['states'];
            $this->lastSnapshot = $states['lastSnapshot'] ?? $this->lastSnapshot;
            $this->ipAddress = $states['ipAddress'] ?? $this->ipAddress;
            $this->moveDetected = $states['moveDetected'] ?? $this->moveDetected;
            $this->eatDetected = $states['eatDetected'] ?? $this->eatDetected;
            $this->petDetected = $states['petDetected'] ?? $this->petDetected;
        }

        // Load settings
        if (isset($config['settings'])) {
            $settings = $config['settings'];

            $this->shareOpen = $settings['shareOpen'] ?? $this->shareOpen;
            $this->factor = $settings['factor'] ?? $this->factor;
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

    /**
     * Convert the configuration to an array
     */
    public function toArray(): array
    {
        return [
            'states' => [
                'error' => $this->error,
                'state' => $this->workingState,
                'ipAddress' => $this->ipAddress,
                'lastSnapshot' => $this->lastSnapshot,
                'moveDetected' => $this->moveDetected,
                'eatDetected' => $this->eatDetected,
                'petDetected' => $this->petDetected,
            ],
            'settings' => [
                'shareOpen' => $this->shareOpen,
                'factor' => $this->factor,
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


}
