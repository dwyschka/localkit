<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\OtaResource;
use App\Http\Resources\MQTT\PropertySet;
use App\Models\Device;

class PropertySetMessage
{

    public static function send(Device $device, array $changes): AnswerDTO {
        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/property/set', $device->productKey(), $device->deviceName()),
            message: (PropertySet::make($device))->setPayload(['property' => $changes]),
        );
    }
}
