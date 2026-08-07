<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\ResetCyclePump;
use App\Models\Device;

class ResetCyclePumpMessage
{
    public static function send(Device $device): AnswerDTO {
        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/reset_cycle_pump', $device->productKey(), $device->deviceName()),
            message: ResetCyclePump::make($device),
        );
    }
}
