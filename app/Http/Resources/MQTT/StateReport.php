<?php

namespace App\Http\Resources\MQTT;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StateReport extends JsonResource
{

    public function toArray(Request $request)
    {
        return [
            "msgType" => 0,
            "payload" => [
                "interval" => 3600,
                "time" => Carbon::now()->format('Y-m-d\TH:i:s.vO')
            ],
            "type" => "t4_state_report",
            'timestamp' => time()
        ];
    }

}
