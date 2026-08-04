<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevReboot extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => (string)time(),
            'version' => '1.0',
            'method'  => 'thing.service.property.set',
            'params'  => [
                'msgType' => 'devreboot',
                'time'    => time(),
            ],
        ];
    }
}