<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceContinue extends JsonResource
{
    public function setPayload($payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.continue',
            'id' => (string)time(),
            "params" => [
                "continue_action" => $this->payload['action']->continue,
            ],
            "version" => "1.0.0",
        ];
    }

}
