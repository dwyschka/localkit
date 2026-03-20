<?php

namespace App\Petkit\BluetoothDevices\K3;

use App\Petkit\BluetoothDevices\DeviceInterface;

class Device implements DeviceInterface
{

    public function hasAction(string $action): bool
    {
        return false;
    }
}
