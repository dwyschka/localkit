<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Legacy `dev_upload_file_info` endpoint. The cloud binary still calls this
 * one for lapse/timelapse recordings, with a single `fileInfo` JSON object
 * instead of v2's `fileInfos` array. Normalise it to the v2 handler so both
 * protocols share the decrypt/remux path.
 */
class DevUploadFileInfoController extends Controller
{
    public function __invoke(
        string $deviceType,
        Request $request,
        DevUploadFileInfoV2Controller $v2,
    ): JsonResponse {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        $fileInfo = json_decode((string) $request->input('fileInfo', '[]'), true);
        $fileInfos = is_array($fileInfo) ? [$fileInfo] : [];

        Log::info('dev_upload_file_info received', [
            'deviceId' => $deviceId,
            'fileInfos' => $fileInfos,
        ]);

        return $v2->processFileInfos($deviceType, $deviceId, $device, $fileInfos);
    }
}
