<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevFeedGet extends JsonResource
{
    public function toArray(Request $request)
    {
        $definition = $this->resource->definition();

        // T6/T7 use the feeder-style `toFeed()` string. The T5 has its own
        // flat schedule shape and exposes it through toFeedGet(), since it
        // does not implement toFeed() at all.
        $feed = method_exists($definition, 'toScheduleGet')
            ? json_encode($definition->toFeedGet())
            : $definition->toFeed($this->resource);

        return [
            'msgType' => 0,
            'payload' => [
                'dataType' => 'dev_feed_get',
                'feed' => $feed,
            ],
            'type' => sprintf('%s_data_get', $this->resource->device_type),
            'timestamp' => time(),
        ];
    }
}
