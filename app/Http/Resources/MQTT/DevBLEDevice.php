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
                        "id" => 400062319,
                        "secret" => "38091608fb65",
                        "type" => 14,
                        "mac" => "a4c138bc947e"
                    ]
                ]
            ],
            "type" => "t4_data_get",
            'timestamp' => time()
        ];
    }

}
