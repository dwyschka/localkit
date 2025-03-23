<?php

namespace App\Http\Resources\MQTT;

use AllowDynamicProperties;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

#[AllowDynamicProperties] class PropertySet extends JsonResource
{
    public function setPayload($payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.property.set',
            'id' => (string)time(),
            "params" => [
                ...$this->payload['property']
            ],
            "version" => "1.0.0",
        ];
    }

}
