<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\ServiceConnect;
use App\Models\BluetoothDevice;
use App\Models\Device;

class ServiceConnectMessage
{

    public static function send(Device $device, BluetoothDevice $btDevice): AnswerDTO {

        $action = new \stdClass();

        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/connect', $device->productKey(), $device->deviceName()),
            message: (ServiceConnect::make($device))->setBluetoothDevice($btDevice),
        );
    }
}
