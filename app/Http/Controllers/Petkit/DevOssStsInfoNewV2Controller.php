<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Petkit\Storage\DeviceObjectStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevOssStsInfoNewV2Controller extends Controller
{
    public function __construct(private readonly DeviceObjectStorage $storage)
    {
    }

    public function __invoke(string $deviceType, Request $request): JsonResponse
    {
        if (! config('localkit.storage.enabled')) {
            return new JsonResponse(['result' => []]);
        }

        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        return new JsonResponse([
            'result' => $this->storage->capabilityResponse($deviceType, $device),
        ]);
    }
}
