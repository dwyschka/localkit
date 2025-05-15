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

    #[Select(
        technicalName: 'sand_type',
        name: 'Litter Type',
        options: [
            'Betonit/Mineral',
            'Tofu',
            'Sand'
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
                  {"sandType": 3}
                {% endif %}
        ',
        entityCategory: 'config'
    )]
    private int $sandType = 0;

    #[HASwitch(
        technicalName: 'manual_lock',
        name: 'Child lock',
        commandTopic: 'setting/set',
        icon: 'mdi:lock',
        valueTemplate: '{{ value_json.settings.manualLock }}',
        commandTemplate: '{"manualLock":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config'
    )]
    private int $manualLock = 0;

    #[HASwitch(
        technicalName: 'click_ok_enable',
        name: 'Click OK Enable',
        commandTopic: 'setting/set',
        icon: 'mdi:help',
        valueTemplate: '{{ value_json.settings.clickOkEnable }}',
        commandTemplate: '{"clickOkEnable":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config'
    )]
    private int $clickOkEnable = 1;

    #[HASwitch(
        technicalName: 'display',
        name: 'Display',
        commandTopic: 'setting/set',
        icon: 'mdi:monitor',
        valueTemplate: '{{ value_json.settings.lightMode }}',
        commandTemplate: '{"lightMode":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config'
    )]
    private int $lightMode = 1;
    private array $lightRange = [0, 1440];

    #[HASwitch(
        technicalName: 'auto_work',
        name: 'Auto clean',
        commandTopic: 'setting/set',
        icon: 'mdi:broom',
        valueTemplate: '{{ value_json.settings.autoWork }}',
        commandTemplate: '{"autoWork":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config'
    )]
    private int $autoWork = 1;
    private int $fixedTimeClear = 0;

    #[HASwitch(
        technicalName: 'downpos',
        name: 'Continous Rotation',
        commandTopic: 'setting/set',
        icon: 'mdi:cached',
        valueTemplate: '{{ value_json.settings.downpos }}',
        commandTemplate: '{"downpos":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config'
    )]
    private int $downpos = 0;
    private int $deepRefresh = 0;
    private int $autoIntervalMin = 0;
    private int $stillTime = 30;

    #[Select(
        technicalName: 'unit',
        name: 'Unit',
        options: [
            'Kilogram',
            'Pound',
        ],
        commandTopic: 'setting/set',
        icon: 'mdi:weight',
        valueTemplate: '
                {% if value_json.settings.unit == 0 %}
                  Kilogram
                {% else %}
                  Pound
                {% endif %}
        ',
        commandTemplate: '
                {% if value == "Pound" %}
                  {"unit": 0}
                {% else %}
                  {"unit": 1}
                {% endif %}
        ',
        entityCategory: 'config'
    )]
    private int $unit = 0;

    #[Select(
        technicalName: 'language',
        name: 'Language',
        options: [
            'English',
            'German',
            'Spanish',
            'Chinese',
            'Italiano',
            'Japanese',
            'Portuguese',
            'Turkish',
            'Russian',
            'French',
        ],
        commandTopic: 'setting/set',
        icon: 'mdi:information-outline',
        valueTemplate: '
                {% if value_json.settings.language == "en_US" %}
                    English
                {% elif value_json.settings.language == "de_DE" %}
                    German
                {% elif value_json.settings.language == "es_ES" %}
                    Spanish
                {% elif value_json.settings.language == "zh_CN" %}
                    Chinese
                {% elif value_json.settings.language == "ja_JP" %}
                    Japanese
                {% elif value_json.settings.language == "it_IT" %}
                    Italiano
                {% elif value_json.settings.language == "pt_PT" %}
                    Portuguese
                {% elif value_json.settings.language == "tr_TR" %}
                    Turkish
                {% elif value_json.settings.language == "ru_RU" %}
                    Russian
                {% else %}
                    French
                {% endif %}
        ',
        commandTemplate: '
{% if value == "English" %}
    {"language": "en_US"}
{% elif value == "German" %}
    {"language": "de_DE"}
{% elif value == "Spanish" %}
    {"language": "es_ES"}
{% elif value == "Chinese" %}
    {"language": "zh_CN"}
{% elif value == "Japanese" %}
    {"language": "js_JP"}
{% elif value == "Italiano" %}
    {"language": "it_IT"}
{% elif value == "Portuguese" %}
    {"language": "pt_PT"}
{% elif value == "Turkish" %}
    {"language": "tr_TR"}
{% elif value == "Russian" %}
    {"language": "ru_RU"}
{% else %}
    {"language": "fr_FR"}
{% endif %}
        ',
        entityCategory: 'config'
    )]
    private string $language = 'de_DE';

    #[HASwitch(
        technicalName: 'avoid_repeat',
        name: 'Avoid repeat cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:repeat',
        valueTemplate: '{{ value_json.settings.avoidRepeat }}',
        commandTemplate: '{"avoidRepeat":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config'
    )]
    private int $avoidRepeat = 1;

    #[Button(
        technicalName: 'action_reset_n50',
        name: 'Reset N50',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "reset_n50"}',
        availabilityTemplate: 'online',
    )]
    private $actionResetN50 = 9;

    #[Button(
        technicalName: 'action_maintenance_start',
        name: 'Maintenance Start',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "start_maintenance"}',
        availabilityTemplate: '{% if value_json.states.state == "IDLE" %}online{% else %}offline{% endif %}',
    )]
    private $actionMaintenance = 9;

    #[Button(
        technicalName: 'action_maintenance_stop',
        name: 'Maintenance Stop',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "stop_maintenance"}',
        availabilityTemplate: '{% if value_json.states.state == "MAINTENANCE" %}online{% else %}offline{% endif %}',
    )]
    private $actionStopMaintenance = 9;

    #[Button(
        technicalName: 'action_cleaning_start',
        name: 'Cleaning Start',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "start_cleaning"}',
        availabilityTemplate: '{% if value_json.states.state == "IDLE" %}online{% else %}offline{% endif %}',
    )]
    private $actionCleaning = 1;

    #[Button(
        technicalName: 'action_dump_litter',
        name: 'Dump Litter',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "dump_litter"}',
        availabilityTemplate: '{% if value_json.states.state == "IDLE" %}online{% else %}offline{% endif %}',
    )]
    private $actionDumpLitter = 1;

    #[HASwitch(
        technicalName: 'underweight',
        name: 'Underweight',
        commandTopic: 'setting/set',
        icon: 'mdi:feather',
        valueTemplate: '{{ value_json.settings.underweight }}',
        commandTemplate: '{"underweight":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config'
    )]
    private int $underweight = 0;

    #[HASwitch(
        technicalName: 'kitten',
        name: 'Kittenmode',
        commandTopic: 'setting/set',
        icon: 'mdi:cat',
        valueTemplate: '{{ value_json.settings.kitten }}',
        commandTemplate: '{"kitten":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config'
    )]
    private int $kitten = 0;
    private int $stopTime = 600;
    private array $sandFullWeight = [3200, 5800, 3000, 3200, 3200];

    private array $k3Device = [];

    #[HASwitch(
        technicalName: 'disturb_mode',
        name: 'Do not disturb',
        commandTopic: 'setting/set',
        icon: 'mdi:bell',
        valueTemplate: '{{ value_json.settings.disturbMode }}',
        commandTemplate: '{"disturbMode":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config'
    )]
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

    #[HASwitch(
        technicalName: 'deep_clean',
        name: 'Deep Cleaning',
        commandTopic: 'setting/set',
        icon: 'mdi:broom',
        valueTemplate: '{{ value_json.settings.deepClean }}',
        commandTemplate: '{"deep_clean":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config'
    )]
    private int $deepClean = 0;
    private int $removeSand = 1;
    private int $bury = 0;
    private int $petInTipLimit = 15;


    #[Sensor(
        technicalName: 'durability_in_days',
        name: 'Next N50 Change in Days',
        icon: 'mdi:update',
        valueTemplate: '{{ ((value_json.consumables.n50_next_change - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    private mixed $n50NextChange = 0;


    #[Number(
        technicalName: 'n50_durability',
        name: 'N50 Durability',
        commandTopic: 'setting/set',
        icon: 'mdi:diamond-stone',
        valueTemplate: '{{ value_json.consumables.n50_durability }}',
        commandTemplate: '{"n50Durability":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    private int $n50Durability = 30;

    private int $k3Id = 0;

    #[Sensor(
        technicalName: 'k3_battery',
        name: 'K3 Battery',
        icon: 'mdi:update',
        stateClass: 'measurement',
        deviceClass: 'battery',
        unitOfMeasurement: '%',
        valueTemplate: '{{ value_json.consumables.k3_battery }}',
        entityCategory: 'diagnostic'
    )]
    private $k3Battery = 100;
    /**
     * @var int|mixed
     */

    #[Sensor(
        technicalName: 'k3_liquid',
        name: 'K3 Liquid',
        icon: 'mdi:update',
        stateClass: 'measurement',
        unitOfMeasurement: '%',
        valueTemplate: '{{ value_json.consumables.k3_liquid }}',
        entityCategory: 'diagnostic'
    )]
    private int $k3Liquid = 100;

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

        // Litter
        $config = $this->device->configuration;
        if (isset($config['litter'])) {
            $this->litterWeight = $config['litter']['weight'] ?? 0;
            $this->litterUsedTimes = $this->device->histories()->whereDate('created_at', now()->toDateTime())->where('type', '=', 'IN_USE')->count();
            $this->litterPercent = $config['litter']['percent'] ?? 100;
        }

        if (isset($config['consumables'])) {
            $this->n50Durability = $config['consumables']['n50_durability'] ?? 30;
            $this->n50NextChange = $config['consumables']['n50_next_change'] ?? 0;
            $this->k3Battery = $config['consumables']['k3_battery'] ?? 100;
            $this->k3Liquid = $config['consumables']['k3_liquid'] ?? 100;
        }

        // Settings
        if (isset($config['settings'])) {
            $settings = $config['settings'];

            $this->shareOpen = $settings['shareOpen'] ?? 0;
            $this->withK3 = $settings['withK3'] ?? 0;
            $this->relateK3Switch = 1;
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
            $this->lightest = $settings['lightest'] ?? 1840;
            $this->deepClean = $settings['deepClean'] ?? 0;
            $this->removeSand = $settings['removeSand'] ?? 1;
            $this->bury = $settings['bury'] ?? 0;
            $this->petInTipLimit = $settings['petInTipLimit'] ?? 15;
            $this->k3Device = $settings['k3Device'] ?? [];

            if(isset($settings['k3Device']['id'])) {
                $this->k3Id = (int)$settings['k3Device']['id'];
            }
        }
    }

    /**
     * Convert the configuration to an array
     */
    public function toArray(): array
    {
        return [
            'k3Device' => [
                'id' => $this->k3Device['id'] ?? '',
                'mac' => $this->k3Device['mac'] ?? '',
                'sn' => $this->k3Device['sn'] ?? '',
                'secret' => $this->k3Device['secret'] ?? '',
            ],
            'consumables' => [
                'n50_durability' => $this->n50Durability,
                'n50_next_change' => $this->n50NextChange,
                'k3_liquid' => $this->k3Liquid,
                'k3_battery' => $this->k3Battery
            ],
            'states' => [
                'error' => $this->error,
                'state' => $this->workingState,
            ],
            'litter' => [
                'weight' => $this->litterWeight,
                'usedTimes' => $this->device->histories()->whereDate('created_at', now()->toDateTime())->where('type', '=', 'IN_USE')->count(),
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

    public function getN50Durability(): int
    {
        return $this->n50Durability;
    }

    public function setN50Durability(int $n50Durability): void
    {
        $this->n50Durability = $n50Durability;
    }

    public function getK3Device(): array
    {
        return $this->k3Device;
    }

    public function setK3Device(array $k3Device): void
    {
        $this->k3Device = $k3Device;
    }

    public function getK3Id(): int
    {
        return $this->k3Id;
    }

    public function setK3Id(int $k3Id): void
    {
        $this->k3Id = $k3Id;
    }


    public function getK3Battery(): int
    {
        return $this->k3Battery;
    }

    public function setK3Battery(int $k3Battery): void
    {
        $this->k3Battery = $k3Battery;
    }

    public function getK3Liquid(): int
    {
        return $this->k3Liquid;
    }

    public function setK3Liquid(int $k3Liquid): void
    {
        $this->k3Liquid = $k3Liquid;
    }
}
