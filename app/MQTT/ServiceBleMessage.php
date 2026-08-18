<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\ServiceBle;
use App\Models\BluetoothDevice;
use App\Models\Device;

class ServiceBleMessage
{

    public static function send(Device $device, BluetoothDevice $btDevice, string $rawCommand, int $cmd): AnswerDTO {

        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/ble', $device->productKey(), $device->deviceName()),
            message: (ServiceBle::make($device))->setBluetoothDevice($btDevice)->setRawCommand($rawCommand, $cmd),
        );
    }
}
