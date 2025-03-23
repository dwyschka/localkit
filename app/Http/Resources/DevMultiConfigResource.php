<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DevMultiConfigResource extends PetkitHttpResource
{
    public static $wrap = 'result';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $setting = $this->resource->configuration['settings'];

        return [
            'lightMultiRange' => $setting['lightRange'] ?? [],
            'distrubMultiRange' => $setting['distrubRange'] ?? [],
        ];
    }
}
