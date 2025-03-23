<?php

namespace App\Http\Resources\MQTT;

use App\Helpers\MQTTTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OtaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => 1004,
            'id' => $this->id,
            'message' => 'version不能为空'
        ];
    }

}
