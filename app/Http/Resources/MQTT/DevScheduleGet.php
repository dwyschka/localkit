<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevScheduleGet extends JsonResource
{
    public function toArray(Request $request)
    {
        $definition = $this->resource->definition();

        // The T5 models its own flat schedule (id/type/time/repeats); every
        // other type keeps the legacy placeholder payload.
        $schedule = method_exists($definition, 'toScheduleGet')
            ? $definition->toScheduleGet()
            : [
                [
                    'repeats' => '1,2,3,4,5,6,7',
                    'id' => 470613,
                    'time' => 460,
                    'type' => 0,
                ],
                [
                    'repeats' => '1,2,3,4,5,6,7',
                    'id' => 470614,
                    'time' => 700,
                    'type' => 0,
                ],
                [
                    'repeats' => '1,2,3,4,5,6,7',
                    'id' => 470615,
                    'time' => 1000,
                    'type' => 0,
                ],
            ];

        return [
            'msgType' => 0,
            'payload' => [
                'schedule' => $schedule,
                'dataType' => 'dev_schedule_get',
            ],
            'type' => sprintf('%s_data_get', $this->resource->device_type),
            'timestamp' => time(),
        ];
    }
}
