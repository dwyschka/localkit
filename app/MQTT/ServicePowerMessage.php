<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\ServicePower;
use App\Models\Device;

class ServicePowerMessage
{
    public static function send(Device $device, int $powerAction): AnswerDTO
    {
        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/power', $device->productKey(), $device->deviceName()),
            message: (ServicePower::make($device))->setPayload(['power_action' => $powerAction]),
        );
    }
}
