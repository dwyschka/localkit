<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\OtaResource;
use App\Models\Device;

class OtaMessage
{

    public static function send(Device $device): AnswerDTO {
        /** @var {"code":1004,"id":"10","message":"version不能为空"} $message */

        $msg = OtaResource::make($device);


        return new AnswerDTO(
            topic: sprintf('/ota/device/upgrade/%s/%s', $device->productKey(), $device->deviceName()),
            message: $msg,
        );
    }
}

