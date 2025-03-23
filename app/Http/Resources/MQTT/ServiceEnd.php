<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceEnd extends JsonResource
{
    public function setPayload($payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.end',
            'id' => (string)time(),
            "params" => [
                "end_action" => $this->payload['action']->end,
            ],
            "version" => "1.0.0",
        ];
    }

}
