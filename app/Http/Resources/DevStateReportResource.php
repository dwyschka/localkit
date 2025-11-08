<?php

namespace App\Http\Resources;

use App\Models\Device;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DevStateReportResource extends PetkitHttpResource
{
    public static $wrap = 'result';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Device $device */
        $device = $this->resource;

        $time = now();
        $time->setTimezone(new DateTimeZone($device->locale));

        return [
            "interval" => 3600,
            "time" => $time->format('Y-m-d\TH:i:s.vP')
        ];
    }
}
