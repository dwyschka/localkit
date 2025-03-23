<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\OtaResource;
use App\Http\Resources\MQTT\PropertySet;
use App\Http\Resources\MQTT\ServiceEnd;
use App\Http\Resources\MQTT\ServiceStart;
use App\Models\Device;

class ServiceEndMessage
{

    public static function send(Device $device, $service = 0): AnswerDTO {
        $action = new \stdClass();
        $action->end = $service;

        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/end', $device->productKey(), $device->deviceName()),
            message: (ServiceEnd::make($device))->setPayload(['action' => $action]),
        );
    }
}
