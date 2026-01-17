<?php

namespace App\Petkit\Devices\Configuration;

use App\DTOs\DeviceConfigurationDTO;
use App\DTOs\K3ConfigDTO;
use App\DTOs\MultiRangeDTO;
use App\DTOs\SandFullWeightDTO;
use App\Models\Device;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;

class PetkitPuraMax extends DeviceConfigurationDTO implements ConfigurationInterface
{

    public ?string $error;
    public ?string $workingState;
    public int $litterWeight;
    public int $litterUsedTimes;
    public int $litterPercent;

    public bool $shareOpen;
    public bool $withK3;
    public int $typeCode;
    public int $sandType;
    public bool $manualLock;
    public bool $clickOkEnable;
    public bool $lightMode;
    public bool $autoWork;
    public int $fixedTimeClear;
    public bool $downpos;
    public bool $deepRefresh;
    public int $autoIntervalMin;
    public int $stillTime;
    public int $unit;
    public string $language;
    public bool $avoidRepeat;
    public bool $underweight;
    public bool $kitten;
    public int $stopTime;
    public SandFullWeightDTO $sandFullWeight;
    public bool $disturbMode;

    public MultiRangeDTO $disturbMultiRange;
    public MultiRangeDTO $lightMultiRange;


    public array $sandSetUseConfig;
    public K3ConfigDTO $k3Config;
    public bool $relateK3Switch;
    public int $lightest;
    public bool $deepClean;
    public bool $removeSand;
    public bool $bury;
    public int $petInTipLimit;
    public int $n50Durability;
    public mixed $n50NextChange;
    public int $k3Battery;
    public int $k3Liquid;
    public ?string $k3Secret;
    public ?int $k3Id;
    public ?string $k3SerialNumber;
    public ?string $k3Mac;

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
            'deepRefresh' => ['bool'],
            'autoIntervalMin' => ['integer', 'min:0'],
            'stillTime' => ['integer', 'min:0'],
            'unit' => ['bool'],
            'language' => ['string', 'in:en_US,de_DE,es_ES,zh_CN,ja_JP,it_IT,pt_PT,tr_TR,ru_RU,fr_FR'],
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
            'workingState' => null,
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
        $data['litterUsedTimes'] = $config['litter']['usedTimes'] ?? null;
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

    public function getDevice()
    {
        // TODO: Implement getDevice() method.
    }
}
