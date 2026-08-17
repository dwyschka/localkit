<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Discern extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.discern',
            'id' => (string) time(),
            "params" => [
                "discern_get" => 1,
            ],
            "version" => "1.0.0",
        ];
    }
}
