<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevDiscernPicResource;
use App\Models\Device;
use Illuminate\Http\Request;

/**
 * Pet recognition reference pictures (per-pet "discern" training images) the
 * device shows/matches against. Always answers an empty list for now - no
 * pets have recognition images configured yet.
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
