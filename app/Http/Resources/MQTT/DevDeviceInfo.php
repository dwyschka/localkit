<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevDeviceInfo extends JsonResource
{

    public function toArray(Request $request)
    {
        /** @var \App\Models\Device $this->resource */
        return $this->resource->definition()->toDeviceInfo();
    }

}
