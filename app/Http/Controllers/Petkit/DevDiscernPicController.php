<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevDiscernPicResource;
use App\Models\Device;
use Illuminate\Http\Request;

/**
 * Pet recognition reference pictures (per-pet "discern" training images) the
 * device matches captures against. One entry per Pet with photos uploaded
 * (see PetResource), not scoped to this specific device - pets are shared
 * across the household's devices.
 */
class DevDiscernPicController extends Controller
{
    public function __invoke(Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->first();

        return new DevDiscernPicResource($device);
    }
}
