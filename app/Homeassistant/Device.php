<?php

namespace App\Homeassistant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Device extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'device' => [
                'identifiers' => [
                    $this->petkit_id
                ],
                'name' => $this->name,
                'sw_version' => $this->firmware,
                'model' => $this->resource->definition()->deviceName(),
                'manufacturer' => 'Petkit'
            ],
            'sensors' => []
        ];
    }
}
