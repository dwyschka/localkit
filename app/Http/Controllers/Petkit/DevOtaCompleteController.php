<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Petkit\DeviceStates;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class DevOtaCompleteController extends Controller
{
    public function __invoke(string $deviceType, Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->first();

        Log::info('OTA', [$request->toArray()]);

        if ($device?->ota_state) {
            if ($request->input('success') === '1') {
                $device->update([
                    'ota_state' => 0,
                    'ota_available' => 0,
                    'working_state' => DeviceStates::IDLE->value,
                    'error' => null,
                ]);
                Log::info('Ota Complete', ['device' => $device->id]);
            } else {
                // Failure: errmsg is "otafail-<code>" (OTA-State +0x50, §8).
                // Surface it as the device error so it shows up in HA.
                $device->update([
                    'ota_state' => 0,
                    'working_state' => DeviceStates::IDLE->value,
                    'error' => self::mapOtaFailure($request->input('errmsg')),
                ]);
                Log::warning('Ota Failed', [
                    'device' => $device->id,
                    'errmsg' => $request->input('errmsg'),
                ]);
            }
        }

        $data = ['result' => 'success'];
        $json = json_encode($data);

        return response($json, 200)
            ->header('Content-Type', 'application/json;charset=utf-8')
            ->header('Content-Length', mb_strlen($json, '8bit'));
    }

    /**
     * Maps an "otafail-<code>" errmsg (OTA-State +0x50, see
     * D4_OTA_HTTP_workflow.txt §8) to a device error key.
     */
    private static function mapOtaFailure(?string $errmsg): string
    {
        $code = null;
        if (is_string($errmsg) && preg_match('/otafail-(\d+)/', $errmsg, $m)) {
            $code = (int) $m[1];
        }

        return match ($code) {
            3 => 'ota_task_failed',
            default => 'ota_failed',
        };
    }
}
