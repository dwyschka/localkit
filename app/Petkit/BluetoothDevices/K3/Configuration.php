<?php

namespace App\Petkit\BluetoothDevices\K3;

use App\DTOs\DeviceConfigurationDTO;
use App\Homeassistant\Sensor;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use Illuminate\Support\Facades\Log;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

class Configuration extends DeviceConfigurationDTO implements ConfigurationInterface
{
    #[Sensor(
        technicalName: 'liquid',
        name: 'Liquid',
        icon: 'mdi:information-outline',
        unitOfMeasurement: '%',
        valueTemplate: '{{ value_json.consumables.liquid }}',
        entityCategory: 'diagnostic'
    )]
    public int $liquid;

    #[Sensor(
        technicalName: 'battery',
        name: 'Battery',
        icon: 'mdi:information-outline',
        deviceClass: 'battery',
        unitOfMeasurement: '%',
        valueTemplate: '{{ value_json.consumables.battery }}',
        entityCategory: 'diagnostic'
    )]
    public int $battery;

    #[Sensor(
        technicalName: 'link_with',
        name: 'Link With',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.settings.linkWith }}',
        entityCategory: 'diagnostic'
    )]
    public string $linkWith;
    public array $standard;
    public int $lightness;
    public int $lowVoltage;
    public int $refreshTotalTime;
    public int $singleRefreshTime;
    public int $singleLightTime;



    public function defaults(): array
    {
        return [
            'liquid' => 100,
            'battery' => 100,
            'standard' => [5, 30],
            'lightness' => 100,
            'lowVoltage' => 5,
            'refreshTotalTime' => 11500,
            'singleRefreshTime' => 25,
            'singleLightTime' => 120,
            'link_with' => null
        ];
    }

    protected function rules(): array
    {
        return [
            'liquid' => ['integer', 'min:0', 'max:100'],
            'battery' => ['integer', 'min:0', 'max:100'],
            'standard' => ['array'],
            'lightness' => ['integer', 'min:0', 'max:100'],
            'lowVoltage' => ['integer', 'min:0'],
            'refreshTotalTime' => ['integer', 'min:0'],
            'singleRefreshTime' => ['integer', 'min:0'],
            'singleLightTime' => ['integer', 'min:0'],
            'linkWith' => ['string', 'nullable'],
        ];
    }

    public function casts(): array {
        return [
            'liquid' => new IntegerCast(),
            'battery' => new IntegerCast(),
            'standard' => new ArrayCast(new IntegerCast()),
            'lightness' => new IntegerCast(),
            'lowVoltage' => new IntegerCast(),
            'refreshTotalTime' => new IntegerCast(),
            'singleRefreshTime' => new IntegerCast(),
            'singleLightTime' => new IntegerCast(),
        ];
    }

    public static function fromDevice(Device|BluetoothDevice $device): self
    {
        $config = $device->configuration;
        $data = [];

        $settings = $config['settings'] ?? [];
        $consumables = $config['consumables'] ?? [];

        // Load consumables
        $data['liquid'] = $consumables['liquid'] ?? null;
        $data['battery'] = $consumables['battery'] ?? null;

        $data['standard'] = $settings['standard'] ?? null;
        $data['lightness'] = $settings['lightness'] ?? null;
        $data['lowVoltage'] = $settings['lowVoltage'] ?? null;
        $data['refreshTotalTime'] = $settings['refreshTotalTime'] ?? null;
        $data['singleRefreshTime'] = $settings['singleRefreshTime'] ?? null;
        $data['singleLightTime'] = $settings['singleLightTime'] ?? null;

        $data['linkWith'] = $device->linkWith?->name ?? 'None';

        return new self(array_filter($data, fn($value) => $value !== null));
    }

    public function toArray(): array
    {
        return [
            'consumables' => [
                'liquid' => $this->liquid,
                'battery' => $this->battery,
            ],
            'settings' => [
                'standard' => $this->standard,
                'lightness' => $this->lightness,
                'lowVoltage' => $this->lowVoltage,
                'refreshTotalTime' => $this->refreshTotalTime,
                'singleRefreshTime' => $this->singleRefreshTime,
                'singleLightTime' => $this->singleLightTime,
                'linkWith' => $this->linkWith ?? 'None'
            ]
        ];
    }

    public function toHomeassistant(): array
    {
        return $this->toArray();
    }

}
