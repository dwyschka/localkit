<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevMultiConfig extends JsonResource
{

    public function toArray(Request $request)
    {
        return [
            "msgType" => 0,
            "payload" => [
                "dataType" => "dev_multi_config",
                'lightMultiRange' => [
                    [0,1440]
                ],
                'distrubMultiRange' => [
                    [40,520]
                ]
            ],
            "type" => "t4_data_get",
            'timestamp' => time()
        ];
    }

}
