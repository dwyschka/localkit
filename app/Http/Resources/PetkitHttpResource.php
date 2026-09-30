<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PetkitHttpResource extends JsonResource
{
    public static $wrap = 'result';

    /**
     * What a device is told when it must not get an MQTT connection up: a single
     * label, so it is not a resolvable name at all - unlike the dotted
     * `noresolv.localkit.io` it replaces, which the wildcard record on
     * localkit.io happily answers with a Cloudflare address.
     *
     * Used for both `mqttHost` and `productKey`: the ALI host is built out of
     * the product key, so leaving one of them real would hand the device half a
     * working address.
     */
    protected const MQTT_NO_RESOLV = 'noresolv-localkit-io';

    /**
     * Should this device be kept off MQTT?
     *
     * There is no "do not connect" in the protocol, so the device is given an
     * address it cannot resolve instead. Two states want that:
     *
     *   ota_state    - as it already did before provisioning existed.
     *   provisioning - the telnetd command rides on the HTTP heartbeat, so the
     *                  device has to stay on that loop until the wizard is done.
     */
    protected function mqttShouldFail(): bool
    {
        return (bool) ($this->ota_state || $this->provisioning);
    }

    public function toResponse($request)
    {
        $response = response()->json(
            [
                self::$wrap => $this->resolve($request)
            ],
            200,
            [],
            JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION
        );

        return $response->header('Content-Type', 'application/json;charset=utf-8')
            ->header('Content-Length', strlen($response->getContent()));
    }
}
