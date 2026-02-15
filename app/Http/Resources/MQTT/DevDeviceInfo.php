<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevDeviceInfo extends JsonResource
{

    public function toArray(Request $request)
    {
        /** @var \App\Models\Device $this->resource */
        return [
            'msgType' => 0,
            'payload' => [
                'dataType' => 'dev_device_info',
                'device' => $this->resource->definition()->toDeviceInfo(),
            ],
            'type' => 't4_data_get',
            'timestamp' => time()
        ];
    }

}
