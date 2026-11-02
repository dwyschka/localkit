<?php

namespace App\Http\Resources\MQTT;

use AllowDynamicProperties;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mirrors ServiceStart/ServiceEnd for the `continue` service - resumes a
 * paused cycle (see ServiceStop's docblock for where `continue_action` was
 * confirmed).
 */
#[AllowDynamicProperties] class ServiceContinue extends JsonResource
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
            'id' => (string)(time() * 1000),
            "params" => [
                "continue_action" => $this->payload['action']->continue,
            ],
            "version" => "1.0.0",
        ];
    }

}
