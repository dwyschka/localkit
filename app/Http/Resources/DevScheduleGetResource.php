<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DevScheduleGetResource extends PetkitHttpResource
{
    public static $wrap = 'result';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            [
                'id' => 0,
                'deviceId' => $this->petkit_id,
                'time' => 460,
                'type' => 0,
                'repeats' => '1,2,3,4,5,6,7',
                'updatedAt' => now()->timestamp
            ],             [
                'id' => 0,
                'deviceId' => $this->petkit_id,
                'time' => 700,
                'type' => 0,
                'repeats' => '1,2,3,4,5,6,7',
                'updatedAt' => now()->timestamp
            ],             [
                'id' => 0,
                'deviceId' => $this->petkit_id,
                'time' => 1000,
                'type' => 0,
                'repeats' => '1,2,3,4,5,6,7',
                'updatedAt' => now()->timestamp
            ]

        ];
    }
}
