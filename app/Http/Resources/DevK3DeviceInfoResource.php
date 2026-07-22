<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class DevK3DeviceInfoResource extends PetkitHttpResource
{
    public static $wrap = 'result';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->definition()->toK3DeviceInfo();
    }
}
