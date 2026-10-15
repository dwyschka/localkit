<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceStop extends JsonResource
{
    public function setPayload($payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.stop',
            'id' => (string)time(),
            "params" => [
                "stop_action" => $this->payload['action']->stop,
            ],
            "version" => "1.0.0",
        ];
    }

}
