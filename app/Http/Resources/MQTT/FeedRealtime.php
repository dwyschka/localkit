<?php

namespace App\Http\Resources\MQTT;

use AllowDynamicProperties;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

#[AllowDynamicProperties] class FeedRealtime extends JsonResource
{
    public function setPayload($payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.feed_realtime',
            'id' => (string)time(),
            "params" => [
                "amount" => $this->payload['amount'],
                "id" => sprintf('r_%s_%d-1',date('Ymd'),rand(1000,9999)),
            ],
            "version" => "1.0.0",
        ];
    }

}
