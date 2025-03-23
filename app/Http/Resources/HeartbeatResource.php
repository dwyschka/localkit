<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HeartbeatResource extends PetkitHttpResource
{
    public static $wrap = 'result';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        Log::info('heartbeat');
        $hasAction = false;

        if(!$hasAction) {
            return [
                ['time' => (time() * 1000)]
            ];
        }
        unlink('/tmp/has_action');
        $ts = time();
        return [
            [
                'content' => json_encode([
                    "msgType" => 2,
                    "payload" => [
                        "start_action" => 0
                    ],
                    "type" => "start",
                    "timestamp" => $ts
                ]),
                'time' => (time() * 1000),
                'timestamp' => $ts
            ]
        ];
    }
}
