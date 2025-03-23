<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevServerInfo extends JsonResource
{

    public function toArray(Request $request)
    {
        return [
            "msgType" => 0,
            "payload" => [
                "dataType" => "dev_serverinfo",
                'ipServers' => [
                ],
                'apiServers' => [
                    'http://api.eu-pet.com/6/'
                ],
                'nextTick' => 3600,
                'linked' => 1
            ],
            "type" => "t4_data_get",
            'timestamp' => time()
        ];
    }

}
