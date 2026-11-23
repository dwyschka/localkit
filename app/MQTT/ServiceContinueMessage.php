<?php

namespace App\MQTT;

use stdClass;
use App\Http\Resources\MQTT\ServiceContinue;
use App\Models\Device;

class ServiceContinueMessage
{

    public static function send(Device $device, $service = 0): AnswerDTO {
        $action = new stdClass();
        $action->continue = $service;

        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/continue', $device->productKey(), $device->deviceName()),
            message: (ServiceContinue::make($device))->setPayload(['action' => $action]),
        );
    }
}
