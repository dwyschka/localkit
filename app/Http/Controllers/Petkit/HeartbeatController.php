<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\HeartbeatOtaResource;
use App\Http\Resources\HeartbeatResource;
use App\Http\Resources\HeartbeatTelnetResource;
use App\Models\Device;
use App\Petkit\ProvisioningRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HeartbeatController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {

        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        /*
         * A device Localkit already knows keeps its id and its secret across a
         * re-provisioning, so it can come straight back here without signing up
         * again - and the sign-up is where a run would otherwise recognise it.
         * Its first heartbeat is the same evidence, so the run is claimed here
         * too when it has not been claimed already.
         */
        ProvisioningRegistry::adopt($device);

        // Provisioning outranks OTA: a device still being provisioned has no
        // telnetd yet, and an update that reboots it would only strand it.
        if ($device->ota_state && ! $device->provisioning) {
            return new HeartbeatOtaResource($device);
        }

        $device->update([
            'last_heartbeat' => time()
        ]);

        Log::info('Heartbeat', ['device' => $deviceId, 'request' => $request->all()]);

        // A device we provisioned over BLE has no telnetd running yet. While the
        // flag is set, every heartbeat carries the command to start one, so
        // telnet stays reachable across reboots. Cleared from the device's page.
        if ($device->provisioning) {
            // Remember where this heartbeat came from: the provisioning wizard's
            // telnet check aims here when the device has not reported an address
            // of its own yet. Short-lived - it is only needed for the minute the
            // wizard is watching.
            cache()->put("provisioning:hb-ip:{$device->id}", $request->ip(), now()->addMinutes(30));

            return new HeartbeatTelnetResource($device);
        }

        return new HeartbeatResource($device);
    }
}
