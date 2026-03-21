<?php

namespace App\Petkit\BluetoothDevices\K3;

use App\Models\BluetoothDevice;
use App\Petkit\BluetoothDevices\BluetoothDeviceTrait;
use App\Petkit\BluetoothDevices\DeviceInterface;

class Device implements DeviceInterface
{
    use BluetoothDeviceTrait;

    public function __construct(protected BluetoothDevice $model) {

    }
    public function hasAction(string $action): bool
    {
        return false;
    }

    public function deviceName(): string {
        return 'K3 Spray';
    }

}
