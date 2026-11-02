<?php

namespace App\Http\Resources\MQTT;

use AllowDynamicProperties;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `thing.service.power` - the T5 home screen's power toggle sends
 * `{"power_action":0|1}`; ctrl_t5's parse_service_invoke_msg() reads it from
 * the `power` service topic.
 */
#[AllowDynamicProperties] class ServicePower extends JsonResource
{
    public function setPayload($payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function toArray(Request $request)
    {
        return [
            'method' => 'thing.service.power',
            'id' => (string) (time() * 1000),
            'params' => [
                'power_action' => $this->payload['power_action'],
            ],
            'version' => '1.0.0',
        ];
    }
}
