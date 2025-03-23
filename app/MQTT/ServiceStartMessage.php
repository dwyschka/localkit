<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\OtaResource;
use App\Http\Resources\MQTT\PropertySet;
use App\Http\Resources\MQTT\ServiceStart;
use App\Models\Device;

class ServiceStartMessage
{

    public static function send(Device $device, $service = 0): AnswerDTO {
        $action = new \stdClass();
        $action->start = $service;

        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/start', $device->productKey(), $device->deviceName()),
            message: (ServiceStart::make($device))->setPayload(['action' => $action]),
        );
    }
}
