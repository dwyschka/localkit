<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\ResetLiftValve;
use App\Models\Device;

class ResetLiftValveMessage
{
    public static function send(Device $device): AnswerDTO {
        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/reset_lift_valve', $device->productKey(), $device->deviceName()),
            message: ResetLiftValve::make($device),
        );
    }
}
