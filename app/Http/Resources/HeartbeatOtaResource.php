<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HeartbeatOtaResource extends PetkitHttpResource
{
    public static $wrap = 'result';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $ts = time();

        return [
            [
                'content' => json_encode([
                    "msgType" => 0,
                    "payload" => [
                        "firmwareId" => 33
                    ],
                    "type" => "ota",
                    "timestamp" => $ts
                ]),
                'time' => (time() * 1000),
                'timestamp' => $ts
            ]
        ];
    }
}
