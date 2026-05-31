<?php

namespace App\Http\Resources;

use App\Localkit\OTA;
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

        $firmware = app(OTA::class)->getAvailable($this->resource);

        $data = [
            'content' => json_encode([
                "msgType" => 0,
                "payload" => [
                    "firmwareId" => $firmware['id']
                ],
                "type" => "ota",
                "timestamp" => $ts
            ]),
            'time' => (time() * 1000),
            'timestamp' => $ts
        ];

        Log::info('heartbeat ota', [
            'firmware' => $firmware,
            'data' => $data
        ]);

        return [
            $data
        ];
    }
}
