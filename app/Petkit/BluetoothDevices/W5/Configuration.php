<?php

namespace App\Petkit\BluetoothDevices\W5;

use App\DTOs\DeviceConfigurationDTO;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Devices\Configuration\ConfigurationInterface;

class Configuration extends DeviceConfigurationDTO implements ConfigurationInterface
{
    public function defaults(): array
    {
        return [

        ];
    }

    protected function rules(): array
    {
        return [

        ];
    }

    public function casts(): array {
        return [
        ];
    }

    public static function fromDevice(Device|BluetoothDevice $device): self
    {
        $config = $device->configuration;

        return new self($config);
    }

    public function toArray(): array
    {
        return [

        ];
    }

}
