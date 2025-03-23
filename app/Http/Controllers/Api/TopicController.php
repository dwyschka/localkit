<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\API\TopicApiResource;
use App\Models\Device;
use Illuminate\Support\Facades\Request;

class TopicController
{

    public function __invoke(Request $request, string $serialNumber)
    {
        $device = Device::where('serial_number', $serialNumber)->firstOrFail();
        return TopicApiResource::make($device);
    }
}
