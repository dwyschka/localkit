<?php

namespace App\Petkit\BluetoothDevices\W5;

use App\DTOs\DeviceConfigurationDTO;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\FloatCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

class Configuration extends DeviceConfigurationDTO implements ConfigurationInterface
{
    public bool $powerStatus;
    public int $mode;
    public bool $runningStatus;

    public bool $dndState;
    public bool $doNotDisturbSwitch;
    public int $doNotDisturbTimeOn;
    public string $doNotDisturbTimeOnReadable;
    public int $doNotDisturbTimeOff;
    public string $doNotDisturbTimeOffReadable;

    public int $warningBreakdown;
    public int $warningWaterMissing;
    public int $warningFilter;

    public int $pumpRuntime;
    public string $pumpRuntimeReadable;
    public int $pumpRuntimeToday;
    public string $pumpRuntimeTodayReadable;

    public int $filterPercentage;
    public int $filterTimeLeftDays;

    public int $smartTimeOn;
    public int $smartTimeOff;

    public bool $ledSwitch;
    public int $ledBrightness;
    public int $ledLightTimeOn;
    public string $ledLightTimeOnReadable;
    public int $ledLightTimeOff;
    public string $ledLightTimeOffReadable;

    public float $purifiedWaterLiters;
    public float $purifiedWaterTodayLiters;
    public string $energyConsumedKwh;

    public function defaults(): array
    {
        return [
            'powerStatus' => false,
            'mode' => 1,
            'runningStatus' => false,
            'dndState' => false,
            'doNotDisturbSwitch' => false,
            'doNotDisturbTimeOn' => 1320,
            'doNotDisturbTimeOnReadable' => '22:00',
            'doNotDisturbTimeOff' => 480,
            'doNotDisturbTimeOffReadable' => '08:00',
            'warningBreakdown' => 0,
            'warningWaterMissing' => 0,
            'warningFilter' => 0,
            'pumpRuntime' => 0,
            'pumpRuntimeReadable' => '0 days, 0 hours',
            'pumpRuntimeToday' => 0,
            'pumpRuntimeTodayReadable' => '00:00h',
            'filterPercentage' => 100,
            'filterTimeLeftDays' => 30,
            'smartTimeOn' => 3,
            'smartTimeOff' => 3,
            'ledSwitch' => 0,
            'ledBrightness' => 2,
            'ledLightTimeOn' => 0,
            'ledLightTimeOnReadable' => '00:00',
            'ledLightTimeOff' => 1440,
            'ledLightTimeOffReadable' => '24:00',
            'purifiedWaterLiters' => 0.0,
            'purifiedWaterTodayLiters' => 0.0,
            'energyConsumedKwh' => '0.000000',
        ];
    }

    protected function rules(): array
    {
        return [
            'powerStatus' => ['boolean'],
            'mode' => ['integer', 'min:0'],
            'runningStatus' => ['integer', 'in:0,1'],
            'dndState' => ['boolean'],
            'doNotDisturbSwitch' => ['boolean'],
            'doNotDisturbTimeOn' => ['integer', 'min:0', 'max:1440'],
            'doNotDisturbTimeOnReadable' => ['string'],
            'doNotDisturbTimeOff' => ['integer', 'min:0', 'max:1440'],
            'doNotDisturbTimeOffReadable' => ['string'],
            'warningBreakdown' => ['integer', 'in:0,1'],
            'warningWaterMissing' => ['integer', 'in:0,1'],
            'warningFilter' => ['integer', 'in:0,1'],
            'pumpRuntime' => ['integer', 'min:0'],
            'pumpRuntimeReadable' => ['string'],
            'pumpRuntimeToday' => ['integer', 'min:0'],
            'pumpRuntimeTodayReadable' => ['string'],
            'filterPercentage' => ['numeric', 'min:0', 'max:100'],
            'filterTimeLeftDays' => ['integer', 'min:0'],
            'smartTimeOn' => ['integer', 'min:0'],
            'smartTimeOff' => ['integer', 'min:0'],
            'ledSwitch' => ['boolean'],
            'ledBrightness' => ['integer', 'min:0'],
            'ledLightTimeOn' => ['integer', 'min:0', 'max:1440'],
            'ledLightTimeOnReadable' => ['string'],
            'ledLightTimeOff' => ['integer', 'min:0', 'max:1440'],
            'ledLightTimeOffReadable' => ['string'],
            'purifiedWaterLiters' => ['numeric', 'min:0'],
            'purifiedWaterTodayLiters' => ['numeric', 'min:0'],
            'energyConsumedKwh' => ['string'],
        ];
    }

    public function casts(): array
    {
        return [
            'powerStatus' => new BooleanCast(),
            'mode' => new IntegerCast(),
            'runningStatus' => new BooleanCast(),
            'dndState' => new BooleanCast(),
            'doNotDisturbSwitch' => new BooleanCast(),
            'doNotDisturbTimeOn' => new IntegerCast(),
            'doNotDisturbTimeOnReadable' => new StringCast(),
            'doNotDisturbTimeOff' => new IntegerCast(),
            'doNotDisturbTimeOffReadable' => new StringCast(),
            'warningBreakdown' => new IntegerCast(),
            'warningWaterMissing' => new IntegerCast(),
            'warningFilter' => new IntegerCast(),
            'pumpRuntime' => new IntegerCast(),
            'pumpRuntimeReadable' => new StringCast(),
            'pumpRuntimeToday' => new IntegerCast(),
            'pumpRuntimeTodayReadable' => new StringCast(),
            'filterPercentage' => new IntegerCast(),
            'filterTimeLeftDays' => new IntegerCast(),
            'smartTimeOn' => new IntegerCast(),
            'smartTimeOff' => new IntegerCast(),
            'ledSwitch' => new BooleanCast(),
            'ledBrightness' => new IntegerCast(),
            'ledLightTimeOn' => new IntegerCast(),
            'ledLightTimeOnReadable' => new StringCast(),
            'ledLightTimeOff' => new IntegerCast(),
            'ledLightTimeOffReadable' => new StringCast(),
            'purifiedWaterLiters' => new FloatCast(),
            'purifiedWaterTodayLiters' => new FloatCast(),
            'energyConsumedKwh' => new StringCast(),
        ];
    }

    public static function fromDevice(Device|BluetoothDevice $device): self
    {
        $config = $device->configuration;

        $data = [];

        $status = $config['states'] ?? [];
        $settings = $config['settings'] ?? [];
        $consumables = $config['consumables'] ?? [];
        $stats = $config['stats'] ?? [];

        $data['powerStatus'] = $status['powerStatus'] ?? null;
        $data['mode'] = $status['mode'] ?? null;
        $data['runningStatus'] = $status['runningStatus'] ?? null;

        $data['dndState'] = $status['dndState'] ?? null;
        $data['doNotDisturbSwitch'] = $settings['doNotDisturbSwitch'] ?? null;
        $data['doNotDisturbTimeOn'] = $settings['doNotDisturbTimeOn'] ?? null;
        $data['doNotDisturbTimeOnReadable'] = $settings['doNotDisturbTimeOnReadable'] ?? null;
        $data['doNotDisturbTimeOff'] = $settings['doNotDisturbTimeOff'] ?? null;
        $data['doNotDisturbTimeOffReadable'] = $settings['doNotDisturbTimeOffReadable'] ?? null;

        $data['warningBreakdown'] = $status['warningBreakdown'] ?? null;
        $data['warningWaterMissing'] = $status['warningWaterMissing'] ?? null;
        $data['warningFilter'] = $status['warningFilter'] ?? null;

        $data['pumpRuntime'] = $stats['pumpRuntime'] ?? null;
        $data['pumpRuntimeReadable'] = $stats['pumpRuntimeReadable'] ?? null;
        $data['pumpRuntimeToday'] = $stats['pumpRuntimeToday'] ?? null;
        $data['pumpRuntimeTodayReadable'] = $stats['pumpRuntimeTodayReadable'] ?? null;

        $data['filterPercentage'] = $consumables['filterPercentage'] ?? null;
        $data['filterTimeLeftDays'] = $consumables['filterTimeLeftDays'] ?? null;

        $data['smartTimeOn'] = $settings['smartTimeOn'] ?? null;
        $data['smartTimeOff'] = $settings['smartTimeOff'] ?? null;

        $data['ledSwitch'] = $settings['ledSwitch'] ?? null;
        $data['ledBrightness'] = $settings['ledBrightness'] ?? null;
        $data['ledLightTimeOn'] = $settings['ledLightTimeOn'] ?? null;
        $data['ledLightTimeOnReadable'] = $settings['ledLightTimeOnReadable'] ?? null;
        $data['ledLightTimeOff'] = $settings['ledLightTimeOff'] ?? null;
        $data['ledLightTimeOffReadable'] = $settings['ledLightTimeOffReadable'] ?? null;

        $data['purifiedWaterLiters'] = $stats['purifiedWaterLiters'] ?? null;
        $data['purifiedWaterTodayLiters'] = $stats['purifiedWaterTodayLiters'] ?? null;
        $data['energyConsumedKwh'] = $stats['energyConsumedKwh'] ?? null;

        return new self(array_filter($data, fn($value) => $value !== null));
    }

    public static function fromParser(array $parser): self
    {

        $data['powerStatus'] = (bool)$parser['powerStatus'] ?? null;
        $data['mode'] = $parser['mode'] ?? null;
        $data['runningStatus'] = $parser['runningStatus'] ?? null;

        $data['dndState'] = $parser['dndState'] ?? null;
        $data['doNotDisturbSwitch'] = $parser['doNotDisturbSwitch'] ?? null;
        $data['doNotDisturbTimeOn'] = $parser['doNotDisturbTimeOn'] ?? null;
        $data['doNotDisturbTimeOnReadable'] = $parser['doNotDisturbTimeOnReadable'] ?? null;
        $data['doNotDisturbTimeOff'] = $parser['doNotDisturbTimeOff'] ?? null;
        $data['doNotDisturbTimeOffReadable'] = $parser['doNotDisturbTimeOffReadable'] ?? null;

        $data['warningBreakdown'] = $parser['warningBreakdown'] ?? null;
        $data['warningWaterMissing'] = $parser['warningWaterMissing'] ?? null;
        $data['warningFilter'] = $parser['warningFilter'] ?? null;

        $data['pumpRuntime'] = $parser['pumpRuntime'] ?? null;
        $data['pumpRuntimeReadable'] = $parser['pumpRuntimeReadable'] ?? null;
        $data['pumpRuntimeToday'] = $parser['pumpRuntimeToday'] ?? null;
        $data['pumpRuntimeTodayReadable'] = $parser['pumpRuntimeTodayReadable'] ?? null;

        $data['filterPercentage'] = $parser['filterPercentage'] ?? null;
        $data['filterTimeLeftDays'] = $parser['filterTimeLeftDays'] ?? null;

        $data['smartTimeOn'] = $parser['smartTimeOn'] ?? null;
        $data['smartTimeOff'] = $parser['smartTimeOff'] ?? null;

        $data['ledSwitch'] = $parser['ledSwitch'] ?? null;
        $data['ledBrightness'] = $parser['ledBrightness'] ?? null;
        $data['ledLightTimeOn'] = $parser['ledLightTimeOn'] ?? null;
        $data['ledLightTimeOnReadable'] = $parser['ledLightTimeOnReadable'] ?? null;
        $data['ledLightTimeOff'] = $parser['ledLightTimeOff'] ?? null;
        $data['ledLightTimeOffReadable'] = $parser['ledLightTimeOffReadable'] ?? null;

        $data['purifiedWaterLiters'] = $parser['purifiedWaterLiters'] ?? null;
        $data['purifiedWaterTodayLiters'] = $parser['purifiedWaterTodayLiters'] ?? null;
        $data['energyConsumedKwh'] = $parser['energyConsumedKwh'] ?? null;

        return new self(array_filter($data, fn($value) => $value !== null));
    }

    public function toArray(): array
    {
        return [
            'states' => [
                'powerStatus' => $this->powerStatus,
                'mode' => $this->mode,
                'runningStatus' => $this->runningStatus,
                'dndState' => $this->dndState,
                'warningBreakdown' => $this->warningBreakdown,
                'warningWaterMissing' => $this->warningWaterMissing,
                'warningFilter' => $this->warningFilter,
            ],
            'settings' => [
                'doNotDisturbSwitch' => $this->doNotDisturbSwitch,
                'doNotDisturbTimeOn' => $this->doNotDisturbTimeOn,
                'doNotDisturbTimeOnReadable' => $this->doNotDisturbTimeOnReadable,
                'doNotDisturbTimeOff' => $this->doNotDisturbTimeOff,
                'doNotDisturbTimeOffReadable' => $this->doNotDisturbTimeOffReadable,
                'smartTimeOn' => $this->smartTimeOn,
                'smartTimeOff' => $this->smartTimeOff,
                'ledSwitch' => $this->ledSwitch,
                'ledBrightness' => $this->ledBrightness,
                'ledLightTimeOn' => $this->ledLightTimeOn,
                'ledLightTimeOnReadable' => $this->ledLightTimeOnReadable,
                'ledLightTimeOff' => $this->ledLightTimeOff,
                'ledLightTimeOffReadable' => $this->ledLightTimeOffReadable,
            ],
            'consumables' => [
                'filterPercentage' => $this->filterPercentage,
                'filterTimeLeftDays' => $this->filterTimeLeftDays,
            ],
            'stats' => [
                'pumpRuntime' => $this->pumpRuntime,
                'pumpRuntimeReadable' => $this->pumpRuntimeReadable,
                'pumpRuntimeToday' => $this->pumpRuntimeToday,
                'pumpRuntimeTodayReadable' => $this->pumpRuntimeTodayReadable,
                'purifiedWaterLiters' => $this->purifiedWaterLiters,
                'purifiedWaterTodayLiters' => $this->purifiedWaterTodayLiters,
                'energyConsumedKwh' => $this->energyConsumedKwh,
            ],
        ];
    }
}
