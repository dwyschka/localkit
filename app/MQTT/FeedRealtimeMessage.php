<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\FeedRealtime;
use App\Http\Resources\MQTT\OtaResource;
use App\Http\Resources\MQTT\PropertySet;
use App\Http\Resources\MQTT\ServiceStart;
use App\Models\Device;

class FeedRealtimeMessage
{

    public static function send(Device $device, $amount = 10): AnswerDTO {
        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/feed_realtime', $device->productKey(), $device->deviceName()),
            message: (FeedRealtime::make($device))->setPayload(['amount' => $amount]),
        );
    }
}
