<?php

namespace App\Http\Resources\MQTT;

use AllowDynamicProperties;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mirrors ServiceStart/ServiceEnd for the `stop` service - needed by devices
 * (PurobotUltra/T6) whose companion app pauses an in-progress cycle with a
 * dedicated `thing.service.stop` / `stop_action` message rather than reusing
 * start or end, confirmed from the app's T6HomePresenter.pauseClean()/
 * T6SandDumpingPresenter.pauseClean() and ctrl_t6's parse_service_invoke_msg.
 */
#[AllowDynamicProperties] class ServiceStop extends JsonResource
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
            'id' => (string)(time() * 1000),
            "params" => [
                "stop_action" => $this->payload['action']->stop,
            ],
            "version" => "1.0.0",
        ];
    }

}
