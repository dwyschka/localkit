<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevFeedGet extends JsonResource
{

    public function toArray(Request $request)
    {
        return [
            "msgType" => 0,
            "payload" => [
                "dataType" => "dev_feed_get",
                "feed" => $this->resource->definition()->toFeed()
            ],
            "type" => sprintf('%s_data_get', $this->resource->device_type),
            'timestamp' => time()
        ];
    }

}
