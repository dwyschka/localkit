<?php

namespace App\Petkit\BluetoothDevices;

interface DeviceInterface
{

    public function hasAction(string $action): bool;

}
