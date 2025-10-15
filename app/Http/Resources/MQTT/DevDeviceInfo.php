<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevDeviceInfo extends JsonResource
{

    public function toArray(Request $request)
    {
        return $this->resource->toDeviceInfo();
    }

}
