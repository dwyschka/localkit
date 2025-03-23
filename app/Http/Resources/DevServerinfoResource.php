<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DevServerinfoResource extends PetkitHttpResource
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
            'ipServers' => [
            ],
            'apiServers' => [
                'http://api.eu-pet.com/6/'
            ],
            'nextTick' => 3600,
            'linked' => 1
        ];
    }
}
