<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\DevReboot;
use App\Models\Device;

class RebootMessage
{
    public static function send(Device $device): AnswerDTO
    {
        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/property/set', $device->productKey(), $device->deviceName()),
            message: DevReboot::make($device),
        );
    }
}