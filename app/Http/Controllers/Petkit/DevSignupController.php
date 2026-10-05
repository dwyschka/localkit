<?php

namespace App\Http\Controllers\Petkit;

use Throwable;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevSignupResource;
use App\Models\Device;
use App\Petkit\ProvisioningRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DevSignupController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {


        $update = [
            'firmware' => $request->get('firmware'),
            'mac' => $request->get('mac'),
            // UTC offset in hours (DST-aware) of the device's reported locale
            // (e.g. America/Los_Angeles), falling back to APP_TIMEZONE.
            'timezone' => (function ($tz) {
                try { return \Carbon\Carbon::now($tz)->utcOffset() / 60; }
                catch (\Throwable) { return \Carbon\Carbon::now(config('app.timezone'))->utcOffset() / 60; }
            })((string) $request->get('locale')),
            'locale' => $request->get('locale'),
            'bt_mac' => $request->get('bt_mac'),
            'ap_mac' => $request->get('ap_mac'),
            'chip_id' => $request->get('chipid'),
            'device_type' => $deviceType,
        ];

        Log::info('DevSignupController', $update);

        if($request->get('id')) {
            $update['petkit_id'] = $request->get('id');
        }

        // Everything from here on is wrapped: a device that gets a 500 back from
        // its sign-up has no id, no secret and no way to report the problem, so
        // it goes quiet and the only trace left is this log. An answer it can
        // parse at least ends up in its own logs - and in ours.
        try {
            /** @var Device $device */
            $device = Device::updateOrCreate([
                'serial_number' => $request->get('sn'),
            ], $update);

            // First sight of a device we set up over BLE from the panel: flag it
            // so the heartbeat starts its telnetd. The browser recorded the MAC
            // it saw advertised; match it against either address the device just
            // reported, and consume the mark so a later re-signup does not
            // re-arm telnet.
            // First sight of a device the panel is provisioning: flag it, so the
            // heartbeat starts its telnetd and the wizard knows which device its
            // run turned out to be about.
            ProvisioningRegistry::adopt($device);

            if (empty($device->secret) || empty($device->mqtt_subdomain)) {
                $device->update([
                    'secret' => $device->secret ?: Str::substr(md5(Str::random(16)), 0, 16),
                    'mqtt_subdomain' => $device->mqtt_subdomain ?: 'localkit',
                ]);
            }

            $device->update([
                'configuration' => $device->configuration()->toArray(),
            ]);

            $device->refresh();

            return new DevSignupResource($device);
        } catch (Throwable $e) {
            Log::error('DevSignupController failed', [
                'sn' => $request->get('sn'),
                'device_type' => $deviceType,
                'exception' => $e,
            ]);

            return new JsonResponse(['result' => 'error', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }
}
