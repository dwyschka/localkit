<?php

namespace App\Http\Resources;

use App\Localkit\OTA;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DevOtaResource extends PetkitHttpResource
{
    public static $wrap = null;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(OTA::class)->toOta($this->resource);
    }

}
