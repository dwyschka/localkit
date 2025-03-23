<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevScheduleGet extends JsonResource
{

    public function toArray(Request $request)
    {
        return [
            "msgType" => 0,
            "payload" => [
                "schedule" => [
                    [
                        "repeats" => "1,2,3,4,5,6,7",
                        "id" => 470613,
                        "time" => 460,
                        "type" => 0
                    ],
                    [
                        "repeats" => "1,2,3,4,5,6,7",
                        "id" => 470614,
                        "time" => 700,
                        "type" => 0
                    ],
                    [
                        "repeats" => "1,2,3,4,5,6,7",
                        "id" => 470615,
                        "time" => 1000,
                        "type" => 0
                    ]
                ],
                "dataType" => "dev_schedule_get"
            ],
            "type" => "t4_data_get",
            'timestamp' => time()
        ];
    }

}
