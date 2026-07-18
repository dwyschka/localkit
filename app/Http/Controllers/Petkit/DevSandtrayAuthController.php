<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevOtaCheckResource;
use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevSandtrayAuthController extends Controller
{
    public function __invoke(string $deviceType, Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        return new JsonResponse([
            'result' => [
                'count' => 0,
                'installTime' => Carbon::now()->addDays(-1)->timestamp
            ]
        ]);
    }
}
