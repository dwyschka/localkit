<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Pet-recognition tuning thresholds for the device's own discern algorithm
 * (minimum detected area in pixels, minimum confidence score) - not
 * per-device, confirmed against the real cloud API's response:
 * {"result":{"list":{"area":6000,"score":25.0}}}
 */
class DevDiscernConfigResource extends PetkitHttpResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'list' => [
                'area' => 6000,
                'score' => 25.0,
            ],
        ];
    }
}
