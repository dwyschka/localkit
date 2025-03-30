<?php
namespace App\Petkit\Devices\Configuration;

use App\Homeassistant\BinarySensor;
use App\Homeassistant\Sensor;
use App\Models\Device;

class PetkitPuraMax implements ConfigurationInterface
{
    /**
     * States section
     */
    #[Sensor(
        technicalName: 'error',
        name: 'Error',
        icon: 'mdi:error',
        valueTemplate: '{{ value_json.states.error }}',
        entityCategory: 'diagnostic'
    )]
    private ?string $error = null;
    #[Sensor(
        technicalName: 'device_status',
        name: 'Device Status',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.state }}',
        entityCategory: 'diagnostic'
    )]
    private ?string $workingState = null;

    #[Sensor(
        technicalName: 'litter_weight',
        name: 'Litter Weight',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.litter.weight }}',
        entityCategory: 'diagnostic'
    )]
    private int $litterWeight = 0;

    #[Sensor(
        technicalName: 'used_times',
        name: 'Used Times',
        icon: 'mdi:counter',
        valueTemplate: '{{ value_json.litter.usedTimes }}',
        entityCategory: 'diagnostic'
    )]
    private int $litterUsedTimes = 0;

    #[Sensor(
        technicalName: 'litter_percent',
        name: 'Litter Percentage',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.litter.percent }}',
        entityCategory: 'diagnostic'
    )]
    private int $litterPercent = 100;

    private int $shareOpen = 0;
    private int $withK3 = 0;
    private int $typeCode = 0;
    private int $sandType = 0;
    private int $manualLock = 0;
    private int $clickOkEnable = 1;
    private int $lightMode = 1;
    private array $lightRange = [0, 1440];
    private int $autoWork = 1;
    private int $fixedTimeClear = 0;
    private int $downpos = 0;
    private int $deepRefresh = 0;
    private int $autoIntervalMin = 0;
    private int $stillTime = 30;
    private int $unit = 0;
    private string $language = 'de_DE';
    private int $avoidRepeat = 1;
    private int $underweight = 0;
    private int $kitten = 0;
    private int $stopTime = 600;
    private array $sandFullWeight = [3200, 5800, 3000, 3200, 3200];
    private int $disturbMode = 0;
    private array $disturbRange = [40, 520];
    private array $sandSetUseConfig = [
        [40, 60, 85],
        [40, 60, 85],
        [40, 60, 85],
        [40, 60, 85]
    ];
    private array $k3Config = [
        'config' => [
            'standard' => [5, 30],
            'lightness' => 100,
            'lowVoltage' => 5,
            'refreshTotalTime' => 11500,
            'singleRefreshTime' => 25,
            'singleLightTime' => 120
        ]
    ];
    private int $relateK3Switch = 1;
    private int $lightest = 1840;
    private int $deepClean = 0;
    private int $removeSand = 1;
    private int $bury = 0;
    private int $petInTipLimit = 15;

    /**
     * Constructor that initializes the configuration from a Device object
     */
    public function __construct(private ?Device $device)
    {
        if(!is_null($device)) {
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

        // Litter
        $config = $this->device->configuration;
        if (isset($config['litter'])) {
            $this->litterWeight = $config['litter']['weight'] ?? 0;
            $this->litterUsedTimes = $config['litter']['usedTimes'] ?? 0;
            $this->litterPercent = $config['litter']['percent'] ?? 100;
        }

        // Settings
        if (isset($config['settings'])) {
            $settings = $config['settings'];

            $this->shareOpen = $settings['shareOpen'] ?? 0;
            $this->withK3 = $settings['withK3'] ?? 0;
            $this->typeCode = $settings['typeCode'] ?? 0;
            $this->sandType = $settings['sandType'] ?? 0;
            $this->manualLock = $settings['manualLock'] ?? 0;
            $this->clickOkEnable = $settings['clickOkEnable'] ?? 1;
            $this->lightMode = $settings['lightMode'] ?? 1;
            $this->lightRange = $settings['lightRange'] ?? [0, 1440];
            $this->autoWork = $settings['autoWork'] ?? 1;
            $this->fixedTimeClear = $settings['fixedTimeClear'] ?? 0;
            $this->downpos = $settings['downpos'] ?? 0;
            $this->deepRefresh = $settings['deepRefresh'] ?? 0;
            $this->autoIntervalMin = $settings['autoIntervalMin'] ?? 0;
            $this->stillTime = $settings['stillTime'] ?? 30;
            $this->unit = $settings['unit'] ?? 0;
            $this->language = $settings['language'] ?? 'de_DE';
            $this->avoidRepeat = $settings['avoidRepeat'] ?? 1;
            $this->underweight = $settings['underweight'] ?? 0;
            $this->kitten = $settings['kitten'] ?? 0;
            $this->stopTime = $settings['stopTime'] ?? 600;
            $this->sandFullWeight = $settings['sandFullWeight'] ?? [3200, 5800, 3000, 3200, 3200];
            $this->disturbMode = $settings['disturbMode'] ?? 0;
            $this->disturbRange = $settings['disturbRange'] ?? [40, 520];
            $this->sandSetUseConfig = $settings['sandSetUseConfig'] ?? [
                [40, 60, 85],
                [40, 60, 85],
                [40, 60, 85],
                [40, 60, 85]
            ];
            $this->k3Config = $settings['k3Config'] ?? [
                'config' => [
                    'standard' => [5, 30],
                    'lightness' => 100,
                    'lowVoltage' => 5,
                    'refreshTotalTime' => 11500,
                    'singleRefreshTime' => 25,
                    'singleLightTime' => 120
                ]
            ];
            $this->relateK3Switch = $settings['relateK3Switch'] ?? 1;
            $this->lightest = $settings['lightest'] ?? 1840;
            $this->deepClean = $settings['deepClean'] ?? 0;
            $this->removeSand = $settings['removeSand'] ?? 1;
            $this->bury = $settings['bury'] ?? 0;
            $this->petInTipLimit = $settings['petInTipLimit'] ?? 15;
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
            ],
            'litter' => [
                'weight' => $this->litterWeight,
                'usedTimes' => $this->litterUsedTimes,
                'percent' => $this->litterPercent,
            ],
            'settings' => [
                'shareOpen' => $this->shareOpen,
                'withK3' => $this->withK3,
                'typeCode' => $this->typeCode,
                'sandType' => $this->sandType,
                'manualLock' => $this->manualLock,
                'clickOkEnable' => $this->clickOkEnable,
                'lightMode' => $this->lightMode,
                'lightRange' => $this->lightRange,
                'autoWork' => $this->autoWork,
                'fixedTimeClear' => $this->fixedTimeClear,
                'downpos' => $this->downpos,
                'deepRefresh' => $this->deepRefresh,
                'autoIntervalMin' => $this->autoIntervalMin,
                'stillTime' => $this->stillTime,
                'unit' => $this->unit,
                'language' => $this->language,
                'avoidRepeat' => $this->avoidRepeat,
                'underweight' => $this->underweight,
                'kitten' => $this->kitten,
                'stopTime' => $this->stopTime,
                'sandFullWeight' => $this->sandFullWeight,
                'disturbMode' => $this->disturbMode,
                'disturbRange' => $this->disturbRange,
                'sandSetUseConfig' => $this->sandSetUseConfig,
                'k3Config' => $this->k3Config,
                'relateK3Switch' => $this->relateK3Switch,
                'lightest' => $this->lightest,
                'deepClean' => $this->deepClean,
                'removeSand' => $this->removeSand,
                'bury' => $this->bury,
                'petInTipLimit' => $this->petInTipLimit
            ]
        ];
    }

    /**
     * Save the configuration back to the device
     */
    public function saveToDevice(): void
    {
        // Update the device with the current configuration
        $this->device->error = $this->error;
        $this->device->working_state = $this->workingState;

        // Prepare the configuration array
        $config = $this->device->configuration ?? [];

        $config['litter'] = [
            'weight' => $this->litterWeight,
            'usedTimes' => $this->litterUsedTimes,
            'percent' => $this->litterPercent,
        ];

        $config['settings'] = [
            'shareOpen' => $this->shareOpen,
            'withK3' => $this->withK3,
            'typeCode' => $this->typeCode,
            'sandType' => $this->sandType,
            'manualLock' => $this->manualLock,
            'clickOkEnable' => $this->clickOkEnable,
            'lightMode' => $this->lightMode,
            'lightRange' => $this->lightRange,
            'autoWork' => $this->autoWork,
            'fixedTimeClear' => $this->fixedTimeClear,
            'downpos' => $this->downpos,
            'deepRefresh' => $this->deepRefresh,
            'autoIntervalMin' => $this->autoIntervalMin,
            'stillTime' => $this->stillTime,
            'unit' => $this->unit,
            'language' => $this->language,
            'avoidRepeat' => $this->avoidRepeat,
            'underweight' => $this->underweight,
            'kitten' => $this->kitten,
            'stopTime' => $this->stopTime,
            'sandFullWeight' => $this->sandFullWeight,
            'disturbMode' => $this->disturbMode,
            'disturbRange' => $this->disturbRange,
            'sandSetUseConfig' => $this->sandSetUseConfig,
            'k3Config' => $this->k3Config,
            'relateK3Switch' => $this->relateK3Switch,
            'lightest' => $this->lightest,
            'deepClean' => $this->deepClean,
            'removeSand' => $this->removeSand,
            'bury' => $this->bury,
            'petInTipLimit' => $this->petInTipLimit
        ];

        $this->device->configuration = $config;
    }

    // Getters and setters for all properties

    // States getters and setters
    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;
        return $this;
    }

    public function getWorkingState(): ?string
    {
        return $this->workingState;
    }

    public function setWorkingState(?string $workingState): self
    {
        $this->workingState = $workingState;
        return $this;
    }

    // Litter getters and setters
    public function getLitterWeight(): int
    {
        return $this->litterWeight;
    }

    public function setLitterWeight(int $weight): self
    {
        $this->litterWeight = $weight;
        return $this;
    }

    public function getLitterUsedTimes(): int
    {
        return $this->litterUsedTimes;
    }

    public function setLitterUsedTimes(int $usedTimes): self
    {
        $this->litterUsedTimes = $usedTimes;
        return $this;
    }

    public function getLitterPercent(): int
    {
        return $this->litterPercent;
    }

    public function setLitterPercent(int $percent): self
    {
        $this->litterPercent = $percent;
        return $this;
    }

    // Settings getters and setters
    public function getShareOpen(): int
    {
        return $this->shareOpen;
    }

    public function setShareOpen(int $shareOpen): self
    {
        $this->shareOpen = $shareOpen;
        return $this;
    }

    public function getWithK3(): int
    {
        return $this->withK3;
    }

    public function setWithK3(int $withK3): self
    {
        $this->withK3 = $withK3;
        return $this;
    }

    public function getTypeCode(): int
    {
        return $this->typeCode;
    }

    public function setTypeCode(int $typeCode): self
    {
        $this->typeCode = $typeCode;
        return $this;
    }

    public function getSandType(): int
    {
        return $this->sandType;
    }

    public function setSandType(int $sandType): self
    {
        $this->sandType = $sandType;
        return $this;
    }

    public function getManualLock(): int
    {
        return $this->manualLock;
    }

    public function setManualLock(int $manualLock): self
    {
        $this->manualLock = $manualLock;
        return $this;
    }

    public function getClickOkEnable(): int
    {
        return $this->clickOkEnable;
    }

    public function setClickOkEnable(int $clickOkEnable): self
    {
        $this->clickOkEnable = $clickOkEnable;
        return $this;
    }

    public function getLightMode(): int
    {
        return $this->lightMode;
    }

    public function setLightMode(int $lightMode): self
    {
        $this->lightMode = $lightMode;
        return $this;
    }

    public function getLightRange(): array
    {
        return $this->lightRange;
    }

    public function setLightRange(array $lightRange): self
    {
        $this->lightRange = $lightRange;
        return $this;
    }

    public function getAutoWork(): int
    {
        return $this->autoWork;
    }

    public function setAutoWork(int $autoWork): self
    {
        $this->autoWork = $autoWork;
        return $this;
    }

    public function getFixedTimeClear(): int
    {
        return $this->fixedTimeClear;
    }

    public function setFixedTimeClear(int $fixedTimeClear): self
    {
        $this->fixedTimeClear = $fixedTimeClear;
        return $this;
    }

    public function getDownpos(): int
    {
        return $this->downpos;
    }

    public function setDownpos(int $downpos): self
    {
        $this->downpos = $downpos;
        return $this;
    }

    public function getDeepRefresh(): int
    {
        return $this->deepRefresh;
    }

    public function setDeepRefresh(int $deepRefresh): self
    {
        $this->deepRefresh = $deepRefresh;
        return $this;
    }

    public function getAutoIntervalMin(): int
    {
        return $this->autoIntervalMin;
    }

    public function setAutoIntervalMin(int $autoIntervalMin): self
    {
        $this->autoIntervalMin = $autoIntervalMin;
        return $this;
    }

    public function getStillTime(): int
    {
        return $this->stillTime;
    }

    public function setStillTime(int $stillTime): self
    {
        $this->stillTime = $stillTime;
        return $this;
    }

    public function getUnit(): int
    {
        return $this->unit;
    }

    public function setUnit(int $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getAvoidRepeat(): int
    {
        return $this->avoidRepeat;
    }

    public function setAvoidRepeat(int $avoidRepeat): self
    {
        $this->avoidRepeat = $avoidRepeat;
        return $this;
    }

    public function getUnderweight(): int
    {
        return $this->underweight;
    }

    public function setUnderweight(int $underweight): self
    {
        $this->underweight = $underweight;
        return $this;
    }

    public function getKitten(): int
    {
        return $this->kitten;
    }

    public function setKitten(int $kitten): self
    {
        $this->kitten = $kitten;
        return $this;
    }

    public function getStopTime(): int
    {
        return $this->stopTime;
    }

    public function setStopTime(int $stopTime): self
    {
        $this->stopTime = $stopTime;
        return $this;
    }

    public function getSandFullWeight(): array
    {
        return $this->sandFullWeight;
    }

    public function setSandFullWeight(array $sandFullWeight): self
    {
        $this->sandFullWeight = $sandFullWeight;
        return $this;
    }

    public function getDisturbMode(): int
    {
        return $this->disturbMode;
    }

    public function setDisturbMode(int $disturbMode): self
    {
        $this->disturbMode = $disturbMode;
        return $this;
    }

    public function getDisturbRange(): array
    {
        return $this->disturbRange;
    }

    public function setDisturbRange(array $disturbRange): self
    {
        $this->disturbRange = $disturbRange;
        return $this;
    }

    public function getSandSetUseConfig(): array
    {
        return $this->sandSetUseConfig;
    }

    public function setSandSetUseConfig(array $sandSetUseConfig): self
    {
        $this->sandSetUseConfig = $sandSetUseConfig;
        return $this;
    }

    public function getK3Config(): array
    {
        return $this->k3Config;
    }

    public function setK3Config(array $k3Config): self
    {
        $this->k3Config = $k3Config;
        return $this;
    }

    public function getRelateK3Switch(): int
    {
        return $this->relateK3Switch;
    }

    public function setRelateK3Switch(int $relateK3Switch): self
    {
        $this->relateK3Switch = $relateK3Switch;
        return $this;
    }

    public function getLightest(): int
    {
        return $this->lightest;
    }

    public function setLightest(int $lightest): self
    {
        $this->lightest = $lightest;
        return $this;
    }

    public function getDeepClean(): int
    {
        return $this->deepClean;
    }

    public function setDeepClean(int $deepClean): self
    {
        $this->deepClean = $deepClean;
        return $this;
    }

    public function getRemoveSand(): int
    {
        return $this->removeSand;
    }

    public function setRemoveSand(int $removeSand): self
    {
        $this->removeSand = $removeSand;
        return $this;
    }

    public function getBury(): int
    {
        return $this->bury;
    }

    public function setBury(int $bury): self
    {
        $this->bury = $bury;
        return $this;
    }

    public function getPetInTipLimit(): int
    {
        return $this->petInTipLimit;
    }

    public function setPetInTipLimit(int $petInTipLimit): self
    {
        $this->petInTipLimit = $petInTipLimit;
        return $this;
    }

    public function getDevice()
    {
        return $this->device;
    }
}
