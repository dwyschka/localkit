<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevDiscernPicResource;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

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
        if (config('petkit.fake_discern_req')) {
            $path = base_path('discern.txt');

            if (File::exists($path)) {
                return response(File::get($path))
                    ->header('Content-Type', 'application/json;charset=utf-8');
            }

            Log::warning('FAKE_DISCERN_REQ is enabled but discern.txt is missing, falling back to the generated response', [
                'path' => $path,
            ]);
        }

        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->first();

        return new DevDiscernPicResource($device);
    }
}
