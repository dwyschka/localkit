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
                "feed" => json_encode($this->resource->toFeed())
            ],
            "type" => "t4_data_get",
            'timestamp' => time()
        ];
    }

}
