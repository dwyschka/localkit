<?php

namespace App\Http\Resources\MQTT;

//{"code":200,"data":{},"id":"107","message":"success","method":"thing.event.work_start.post","version":"1.0"}
use App\Helpers\MQTTTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuccessResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $data = new \stdClass();
        return [
            'code' => 200,
            'data' => $data,
            'id' => $this->id,
            'message' => "success",
            'method' => $this->method,
            'version' => "1.0.0"
        ];
    }

}


