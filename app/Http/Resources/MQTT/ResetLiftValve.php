<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `reset_lift_valve` from IMPLEMENT/w7h_actions.csv - matches the app label
 * "Deep Clean" (resets the lift valve to fully drain the tank for cleaning).
 * No JSON params per the firmware analysis.
 */
class ResetLiftValve extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.reset_lift_valve',
            'id' => (string)time(),
            "params" => new \stdClass(),
            "version" => "1.0.0",
        ];
    }
}
