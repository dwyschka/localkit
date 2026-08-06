<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `reset_cycle_pump` from IMPLEMENT/w7h_actions.csv - matches the app label
 * "Drain and Flush" (cycles/resets the circulation pump). No JSON params per
 * the firmware analysis.
 */
class ResetCyclePump extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.reset_cycle_pump',
            'id' => (string)time(),
            "params" => new \stdClass(),
            "version" => "1.0.0",
        ];
    }
}
