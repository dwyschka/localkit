<?php

namespace App\Petkit\BluetoothDevices;

use App\Models\BluetoothDevice;

interface DeviceInterface
{

    public function hasAction(string $action): bool;

    /**
     * Called after the record is saved with a changed `configuration` -
     * compare $device->getOriginal('configuration') against the new
     * $device->configuration and dispatch whatever hardware write the
     * difference implies (e.g. a BLE command). No-op if nothing relevant
     * changed.
     */
    public function propertyChange(BluetoothDevice $device): void;

}
