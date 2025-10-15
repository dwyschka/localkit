<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DevFeedGetResource extends PetkitHttpResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        return [
            'schedule' => [
                [
                    're' => '1,2,3,4,5,6,7',
                    'it' => [],
                    'itemJsonString' => '[]'
                ]
            ],
            'nextTick' => 86340,
            'latest' => []
        ];
    }

}
