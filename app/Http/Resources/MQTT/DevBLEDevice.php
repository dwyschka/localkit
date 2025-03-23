<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevBLEDevice extends JsonResource
{

    public function toArray(Request $request)
    {
        return [
            "msgType" => 0,
            "payload" => [
                "nextTick" => 3600,
                "dataType" => "dev_ble_device",
                "list" => [
                    [
                        "interval" => 240,
                        "id" => 400044053,
                        "secret" => "50fed972f45f",
                        "type" => 14,
                        "mac" => "a4c138a66d88"
                    ]
                ]
            ],
            "type" => "t4_data_get",
            'timestamp' => time()
        ];
    }

}
