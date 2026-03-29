<?php

namespace App\Helpers;

use App\Models\BluetoothDevice;
use App\Models\Device;

class HomeassistantHelper
{

    public static function configTopic(string $entityName, string $entity, Device|BluetoothDevice $device): string{
        return sprintf(
            '%s/%s/%s/%s/config',
            config('petkit.discovery_prefix'),
            $entity,
            $device->serial_number,
            $entityName
        );
    }

    public static function deviceTopic(Device|BluetoothDevice $device): string{
        if($device instanceof BluetoothDevice){
            return sprintf('localkit/%s/%s', $device->type, $device->mac);
        }
        return sprintf('localkit/%s/%s', $device->productKey(), $device->deviceName());
    }

    public static function snapshotTopic(Device|BluetoothDevice $device): string{
        return sprintf('localkit/%s/%s/snapshot', $device->productKey(), $device->deviceName());
    }
}
