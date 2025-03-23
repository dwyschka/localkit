<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DevSignupResource extends PetkitHttpResource
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
            'id' => $this->petkit_id,
            'mac' => $this->mac,
            'sn' => $this->serial_number,
            'secret' => "",
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'shareOpen' => $this->configuration['settings']['shareOpen'],
            'petInTipLimit' => $this->configuration['settings']['petInTipLimit']
        ];
    }
}
