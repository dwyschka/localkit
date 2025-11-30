<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevOtaCheckResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevVideoDeviceInfoController extends Controller
{
    public function __invoke(string $deviceType, Request $request)
    {
        return new JsonResponse([
            'result' => [
                'agora' => [
                    'license' => "",
                    'appId' => "",
                ]
            ]
        ]);
    }
}
