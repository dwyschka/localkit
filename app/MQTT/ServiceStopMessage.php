<?php

namespace App\MQTT;

use stdClass;
use App\Http\Resources\MQTT\ServiceStop;
use App\Models\Device;

class ServiceStopMessage
{

    public static function send(Device $device, $service = 0): AnswerDTO {
        $action = new stdClass();
        $action->stop = $service;

        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/stop', $device->productKey(), $device->deviceName()),
            message: (ServiceStop::make($device))->setPayload(['action' => $action]),
        );
    }
}
