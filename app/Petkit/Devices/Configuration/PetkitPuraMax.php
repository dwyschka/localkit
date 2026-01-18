<?php

namespace App\Petkit\Devices\Configuration;

use App\DTOs\DeviceConfigurationDTO;
use App\DTOs\K3ConfigDTO;
use App\DTOs\MultiRangeDTO;
use App\DTOs\SandFullWeightDTO;
use App\Homeassistant\Button;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Models\Device;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;

class PetkitPuraMax extends DeviceConfigurationDTO implements ConfigurationInterface
{

    #[Sensor(
        technicalName: 'error',
        name: 'Error',
        icon: 'mdi:error',
        valueTemplate: '{{ value_json.states.error }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $error;

    #[Sensor(
        technicalName: 'device_status',
        name: 'Device Status',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.state }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $workingState;

    #[Sensor(
        technicalName: 'litter_weight',
        name: 'Litter Weight',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.litter.weight }}',
        entityCategory: 'diagnostic'
    )]
    public int $litterWeight;

    #[Sensor(
        technicalName: 'used_times',
        name: 'Used Times',
        icon: 'mdi:counter',
        valueTemplate: '{{ value_json.litter.usedTimes }}',
        entityCategory: 'diagnostic'
    )]
    public int $litterUsedTimes;

    #[Sensor(
        technicalName: 'litter_percent',
        name: 'Litter Percentage',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.litter.percent }}',
        entityCategory: 'diagnostic'
    )]
    public int $litterPercent;

    public bool $shareOpen;
    public bool $withK3;
    public int $typeCode;

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
    public int $sandType;

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
    public bool $clickOkEnable;

    #[HASwitch(
        technicalName: 'display',
        name: 'Set Screen Display',
        commandTopic: 'setting/set',
        icon: 'mdi:monitor',
        valueTemplate: '{{ value_json.settings.lightMode }}',
        commandTemplate: '{"lightMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $lightMode;

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
    public int $fixedTimeClear;

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
    public bool $deepRefresh;
    public int $autoIntervalMin;
    public int $stillTime;

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
    public int $unit;

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
    public string $language;

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
    public int $stopTime;
    public SandFullWeightDTO $sandFullWeight;

    #[HASwitch(
        technicalName: 'disturb_mode',
        name: 'Do not disturb',
        commandTopic: 'setting/set',
        icon: 'mdi:bell',
        valueTemplate: '{{ value_json.settings.disturbMode }}',
        commandTemplate: '{"disturbMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $disturbMode;

    public MultiRangeDTO $disturbMultiRange;
    public MultiRangeDTO $lightMultiRange;


    public array $sandSetUseConfig;
    public K3ConfigDTO $k3Config;
    public bool $relateK3Switch;
    public int $lightest;

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
    public bool $removeSand;

    #[HASwitch(
        technicalName: 'bury',
        name: 'Waste Covering',
        commandTopic: 'setting/set',
        icon: 'mdi:broom',
        valueTemplate: '{{ value_json.settings.bury }}',
        commandTemplate: '{"bury":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $bury;
    public int $petInTipLimit;

    #[Number(
        technicalName: 'n50Durability',
        name: 'N50 Durability',
        commandTopic: 'setting/set',
        icon: 'mdi:diamond-stone',
        valueTemplate: '{{ value_json.consumables.n50Durability }}',
        commandTemplate: '{"n50Durability":{{ value }}}',
        payloadOn: 1,
        payloadOff: 0,
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    public int $n50Durability;

    #[Sensor(
        technicalName: 'durabilityInDays',
        name: 'Next N50 Change in Days',
        icon: 'mdi:update',
        valueTemplate: '{{ ((value_json.consumables.n50NextChange - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    public mixed $n50NextChange;

    #[Sensor(
        technicalName: 'k3Battery',
        name: 'K3 Battery',
        icon: 'mdi:update',
        stateClass: 'measurement',
        deviceClass: 'battery',
        unitOfMeasurement: '%',
        valueTemplate: '{{ value_json.consumables.k3Battery }}',
        entityCategory: 'diagnostic'
    )]
    public int $k3Battery;

    #[Sensor(
        technicalName: 'k3Liquid',
        name: 'K3 Liquid',
        icon: 'mdi:update',
        stateClass: 'measurement',
        unitOfMeasurement: '%',
        valueTemplate: '{{ value_json.consumables.k3Liquid }}',
        entityCategory: 'diagnostic'
    )]
    public int $k3Liquid;
    public ?string $k3Secret;
    public ?int $k3Id;
    public ?string $k3SerialNumber;
    public ?string $k3Mac;

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
        technicalName: 'action_lightning_start',
        name: 'Lightning Start',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "start_lightning"}',
        availabilityTemplate: '{% if value_json.states.state == "IDLE" %}online{% else %}offline{% endif %}',
    )]
    private $actionStartLightning = 1;

    #[Button(
        technicalName: 'action_lightning_stop',
        name: 'Lightning Stop',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "stop_lightning"}',
        availabilityTemplate: '{% if value_json.states.state == "IDLE" %}online{% else %}offline{% endif %}',
    )]
    private $actionStopLightning = 1;

    #[Button(
        technicalName: 'action_odour_start',
        name: 'Odour Start',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "start_odour"}',
        availabilityTemplate: '{% if value_json.states.state == "IDLE" %}online{% else %}offline{% endif %}',
    )]
    private $actionStartOdour = 1;

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

    protected function rules(): array
    {
        return [
            'error' => ['nullable', 'string'],
            'workingState' => ['nullable', 'string'],
            'litterWeight' => ['integer', 'min:0'],
            'litterUsedTimes' => ['integer', 'min:0'],
            'litterPercent' => ['integer', 'min:0', 'max:100'],
            'shareOpen' => ['bool'],
            'withK3' => ['bool'],
            'typeCode' => ['integer'],
            'sandType' => ['integer', 'in:0,1,2,3'],
            'manualLock' => ['bool'],
            'clickOkEnable' => ['bool'],
            'lightMode' => ['bool'],
            'autoWork' => ['bool'],
            'fixedTimeClear' => ['integer'],
            'downpos' => ['bool'],
            'language' => ['string', 'min:5', 'max:5'],
            'deepRefresh' => ['bool'],
            'autoIntervalMin' => ['integer', 'min:0'],
            'stillTime' => ['integer', 'min:0'],
            'unit' => ['bool'],
            'avoidRepeat' => ['bool'],
            'underweight' => ['bool'],
            'kitten' => ['bool'],
            'stopTime' => ['integer', 'min:0'],
            'sandFullWeight' => ['array', 'size:5'],
            'sandFullWeight.*' => ['integer', 'min:0'],
            'disturbMode' => ['bool'],
            'disturbMultiRange' => ['array'],
            'lightMultiRange' => ['array'],
            'sandSetUseConfig' => ['array'],
            'relateK3Switch' => ['bool'],
            'lightest' => ['integer', 'min:0'],
            'deepClean' => ['bool'],
            'removeSand' => ['bool'],
            'bury' => ['bool'],
            'petInTipLimit' => ['integer', 'min:0'],
            'n50Durability' => ['integer', 'min:0', 'max:90'],
            'n50NextChange' => ['nullable'],
            'k3Battery' => ['integer', 'min:0', 'max:100'],
            'k3Liquid' => ['integer', 'min:0', 'max:100'],
            'k3Id' => ['nullable', 'integer'],
            'k3SerialNumber' => ['nullable', 'string'],
            'k3Mac' => ['nullable', 'string'],
            'k3Secret' => ['nullable', 'string'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'error' => null,
            'workingState' => 'IDLE',
            'litterWeight' => 0,
            'litterUsedTimes' => 0,
            'litterPercent' => 100,
            'shareOpen' => false,
            'withK3' => false,
            'typeCode' => 0,
            'sandType' => 0,
            'manualLock' => false,
            'clickOkEnable' => true,
            'lightMode' => false,
            'autoWork' => true,
            'fixedTimeClear' => 0,
            'downpos' => false,
            'deepRefresh' => false,
            'autoIntervalMin' => 0,
            'stillTime' => 30,
            'unit' => 0,
            'language' => 'de_DE',
            'avoidRepeat' => true,
            'underweight' => false,
            'kitten' => false,
            'stopTime' => 600,
            'sandFullWeight' => [],
            'disturbMode' => false,
            'disturbMultiRange' => [
                'name' => 'disturbMultiRange',
                'ranges' => [
                    ['from' => 0, 'till' => 1440]
                ]
            ],
            'lightMultiRange' => [
                'name' => 'lightMultiRange',
                'ranges' => [
                   ['from' => 0, 'till' => 1440]
                ]
            ],
            'sandSetUseConfig' => [
                [40, 60, 85],
                [40, 60, 85],
                [40, 60, 85],
                [40, 60, 85]
            ],
            'k3Config' => [],
            'k3Id' => null,
            'k3SerialNumber' => null,
            'k3Mac' => null,
            'k3Secret' => null,
            'relateK3Switch' => false,
            'lightest' => 1840,
            'deepClean' => false,
            'removeSand' => true,
            'bury' => false,
            'petInTipLimit' => 15,
            'n50Durability' => 30,
            'n50NextChange' => 0,
            'k3Battery' => 100,
            'k3Liquid' => 100
        ];
    }

    protected function casts(): array
    {
        return [
            'error' => new StringCast(),
            'workingState' => new StringCast(),
            'litterWeight' => new IntegerCast(),
            'litterUsedTimes' => new IntegerCast(),
            'litterPercent' => new IntegerCast(),
            'shareOpen' => new BooleanCast(),
            'withK3' => new BooleanCast(),
            'typeCode' => new IntegerCast(),
            'sandType' => new IntegerCast(),
            'manualLock' => new BooleanCast(),
            'clickOkEnable' => new BooleanCast(),
            'lightMode' => new BooleanCast(),
            'autoWork' => new BooleanCast(),
            'fixedTimeClear' => new IntegerCast(),
            'downpos' => new BooleanCast(),
            'deepRefresh' => new BooleanCast(),
            'autoIntervalMin' => new IntegerCast(),
            'stillTime' => new IntegerCast(),
            'unit' => new IntegerCast(),
            'language' => new StringCast(),
            'avoidRepeat' => new BooleanCast(),
            'underweight' => new BooleanCast(),
            'kitten' => new BooleanCast(),
            'stopTime' => new IntegerCast(),
            'sandFullWeight' => new DTOCast(SandFullWeightDTO::class),
            'k3Id' => new IntegerCast(),
            'k3SerialNumber' => new StringCast(),
            'k3Mac' => new StringCast(),
            'k3Secret' => new StringCast(),
            'disturbMode' => new BooleanCast(),
            'disturbMultiRange' => new DTOCast(MultiRangeDTO::class),
            'lightMultiRange' => new DTOCast(MultiRangeDTO::class),
            'sandSetUseConfig' => new ArrayCast(),
            'k3Config' => new DTOCast(K3ConfigDTO::class),
            'relateK3Switch' => new BooleanCast(),
            'lightest' => new IntegerCast(),
            'deepClean' => new BooleanCast(),
            'removeSand' => new BooleanCast(),
            'bury' => new BooleanCast(),
            'petInTipLimit' => new IntegerCast(),
            'n50Durability' => new IntegerCast(),
            'k3Battery' => new IntegerCast(),
            'k3Liquid' => new IntegerCast()
        ];
    }

    /**
     * Create DTO from Device model
     */
    public static function fromDevice(Device $device): self
    {
        $config = $device->configuration;

        $data = [];

        $data['workingState'] = $device->working_state;
        $data['error'] = $device->error;

        // Load k3Device
        $data['k3Id'] = $config['k3Device']['id'] ?? null;
        $data['k3SerialNumber'] = $config['k3Device']['serialNumber'] ?? null;
        $data['k3Mac'] = $config['k3Device']['mac'] ?? null;
        $data['k3Secret'] = $config['k3Device']['secret'] ?? null;

        // Load consumables
        $data['n50Durability'] = $config['consumables']['n50Durability'] ?? null;
        $data['n50NextChange'] = $config['consumables']['n50NextChange'] ?? null;
        $data['k3Battery'] = $config['consumables']['k3Battery'] ?? null;
        $data['k3Liquid'] = $config['consumables']['k3Liquid'] ?? null;

        // Load states
        $data['error'] = $config['states']['error'] ?? null;
        $data['workingState'] = $config['states']['state'] ?? null;

        // Load litter
        $data['litterWeight'] = $config['litter']['weight'] ?? null;
        $data['litterUsedTimes'] = $device->histories()->whereDate('created_at', now()->toDateTime())->where('type', '=', 'IN_USE')->count();
        $data['litterPercent'] = $config['litter']['percent'] ?? null;

        // Load settings
        $data['shareOpen'] = $config['settings']['shareOpen'] ?? null;
        $data['withK3'] = $config['settings']['withK3'] ?? null;
        $data['typeCode'] = $config['settings']['typeCode'] ?? null;
        $data['sandType'] = $config['settings']['sandType'] ?? null;
        $data['manualLock'] = $config['settings']['manualLock'] ?? null;
        $data['clickOkEnable'] = $config['settings']['clickOkEnable'] ?? null;
        $data['lightMode'] = $config['settings']['lightMode'] ?? null;
        $data['autoWork'] = $config['settings']['autoWork'] ?? null;
        $data['fixedTimeClear'] = $config['settings']['fixedTimeClear'] ?? null;
        $data['downpos'] = $config['settings']['downpos'] ?? null;
        $data['deepRefresh'] = $config['settings']['deepRefresh'] ?? null;
        $data['autoIntervalMin'] = $config['settings']['autoIntervalMin'] ?? null;
        $data['stillTime'] = $config['settings']['stillTime'] ?? null;
        $data['unit'] = $config['settings']['unit'] ?? null;
        $data['language'] = $config['settings']['language'] ?? null;
        $data['avoidRepeat'] = $config['settings']['avoidRepeat'] ?? null;
        $data['underweight'] = $config['settings']['underweight'] ?? null;
        $data['kitten'] = $config['settings']['kitten'] ?? null;
        $data['stopTime'] = $config['settings']['stopTime'] ?? null;
        $data['sandFullWeight'] = $config['settings']['sandFullWeight'] ?? null;
        $data['disturbMode'] = $config['settings']['disturbMode'] ?? null;
        $data['disturbMultiRange'] = $config['settings']['disturbMultiRange'] ?? null;
        $data['lightMultiRange'] = $config['settings']['lightMultiRange'] ?? null;
        $data['sandSetUseConfig'] = $config['settings']['sandSetUseConfig'] ?? null;
        $data['k3Config'] = $config['settings']['k3Config'] ?? null;
        $data['relateK3Switch'] = $config['settings']['relateK3Switch'] ?? null;
        $data['lightest'] = $config['settings']['lightest'] ?? null;
        $data['deepClean'] = $config['settings']['deepClean'] ?? null;
        $data['removeSand'] = $config['settings']['removeSand'] ?? null;
        $data['bury'] = $config['settings']['bury'] ?? null;
        $data['petInTipLimit'] = $config['settings']['petInTipLimit'] ?? null;


        // Filter out null values to let defaults() handle missing data
        return new self(array_filter($data, fn($value) => $value !== null));
    }

    /**
     * Convert to the array format expected by your system
     */
    public function toArray(): array
    {
        return [
            'k3Device' => [
                'id' => $this->k3Id,
                'serialNumber' => $this->k3SerialNumber,
                'mac' => $this->k3Mac,
                'secret' => $this->k3Secret,
            ],
            'consumables' => [
                'n50Durability' => $this->n50Durability,
                'n50NextChange' => $this->n50NextChange,
                'k3Liquid' => $this->k3Liquid,
                'k3Battery' => $this->k3Battery
            ],
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
                'disturbMultiRange' => $this->disturbMultiRange,
                'lightMultiRange' => $this->lightMultiRange,
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

}
