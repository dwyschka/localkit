<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class DevDiscernPicResource extends PetkitHttpResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'list' => [],
        ];
    }
}
