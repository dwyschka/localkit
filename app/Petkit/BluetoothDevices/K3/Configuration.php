<?php

namespace App\Petkit\BluetoothDevices\K3;

use App\DTOs\DeviceConfigurationDTO;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;

class Configuration extends DeviceConfigurationDTO implements ConfigurationInterface
{
    public int $liquid;
    public int $battery;

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
            'singleLightTime' => 120
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

        // Load consumables
        if (isset($config['consumables'])) {
            $consumables = $config['consumables'];
            $data['liquid'] = $consumables['liquid'] ?? null;
            $data['battery'] = $consumables['battery'] ?? null;
        }

        // Load settings
        if (isset($config['settings'])) {
            $settings = $config['settings'];
            $data['standard'] = $settings['standard'] ?? null;
            $data['lightness'] = $settings['lightness'] ?? null;
            $data['lowVoltage'] = $settings['lowVoltage'] ?? null;
            $data['refreshTotalTime'] = $settings['refreshTotalTime'] ?? null;
            $data['singleRefreshTime'] = $settings['singleRefreshTime'] ?? null;
            $data['singleLightTime'] = $settings['singleLightTime'] ?? null;
        }

        return new self($data);
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
                'singleLightTime' => $this->singleLightTime
            ]
        ];
    }

}
