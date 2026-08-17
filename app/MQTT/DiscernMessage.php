<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\Discern;
use App\Models\Device;

/**
 * `thing.service.discern` - confirmed via live capture (mqtt.txt): tells a
 * NextGen camera device to re-fetch its discern reference photos
 * (dev_discern_pic), so devices actually pick up newly uploaded pet photos
 * instead of waiting for their next own periodic refresh.
 */
class DiscernMessage
{
    public static function send(Device $device): AnswerDTO
    {
        return new AnswerDTO(
            topic: sprintf('/sys/%s/%s/thing/service/discern', $device->productKey(), $device->deviceName()),
            message: Discern::make($device),
        );
    }
}
