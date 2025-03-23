<?php

namespace App\Http\Resources\MQTT;

use AllowDynamicProperties;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

#[AllowDynamicProperties] class ServiceStart extends JsonResource
{
    public function setPayload($payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.start',
            'id' => (string)time(),
            "params" => [
                "start_action" => $this->payload['action']->start,
            ],
            "version" => "1.0.0",
        ];
    }

}
