<?php

namespace App\Http\Resources;

use App\Petkit\ProvisioningRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DevServerinfoResource extends PetkitHttpResource
{
    public static $wrap = 'result';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /*
         * Both lists point here.
         *
         * PetKit's own cloud names the same server twice - the hostname in
         * `apiServers`, its bare address in `ipServers`, so a device with no
         * working DNS still has somewhere to go (examples.txt has a captured
         * response). Localkit used to copy that shape literally and hand back
         * PetKit's hostname, which only ever worked because the classic setup
         * redirects that name in DNS.
         *
         * A device provisioned over BLE has no such redirect - the whole point
         * of provisioning is not needing one - so the hostname resolved for
         * real and sent the device to the cloud, whatever address it had been
         * given over Bluetooth. Its own address is correct in both setups.
         */
        $server = sprintf('http://%s/6/', config('petkit.local_ip'));

        // While a device is being provisioned it gets the address the operator
        // typed into the wizard, which is the one it was handed over Bluetooth.
        // The panel's default is usually the same thing - but not when Localkit
        // is reached through a different address than it knows itself by, and
        // then sending the default here would move the device off the server it
        // just signed up to.
        if ($this->provisioning) {
            $server = ProvisioningRegistry::serverFor($this->id) ?? $server;
        }

        return [
            'ipServers' => [$server],
            'dns' => [],
            'apiServers' => [$server],
            'nextTick' => 3600,
            'linked' => 1
        ];
    }
}
