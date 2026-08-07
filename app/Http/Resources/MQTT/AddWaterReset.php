<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddWaterReset extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.add_water_reset',
            'id' => (string)time(),
            "params" => [
                "add_water_reset_action" => 1,
            ],
            "version" => "1.0.0",
        ];
    }
}
