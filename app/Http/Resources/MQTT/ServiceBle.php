<?php

namespace App\Http\Resources\MQTT;

use App\Models\BluetoothDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relays a raw BLE command frame to a peripheral (e.g. a W5 fountain)
 * through the proxy device's `thing/service/ble` topic.
 *
 * Envelope confirmed from a live capture (cmd 215 / set_light_setting):
 *   {"method":"thing.service.ble","id":"740494448","params":{"payload":
 *   {"data":"+vz91wEFAAD7","cmd":215},"device":{"type":14,"mac":"..."},
 *   "timestamp":1770587250},"version":"1.0.0"}
 * `params.payload.data` is the base64 of the full FA FC FD ... FB frame
 * (see W5\Commands::build()); `params.payload.cmd` duplicates the frame's
 * own cmd byte as a plain int alongside it.
 */
class ServiceBle extends JsonResource
{
    protected BluetoothDevice $bluetoothDevice;
    protected string $commandBase64;
    protected int $cmd;

    public function setBluetoothDevice(BluetoothDevice $bluetoothDevice): self
    {
        $this->bluetoothDevice = $bluetoothDevice;
        return $this;
    }

    /**
     * @param string $commandBase64 Already base64-encoded - the raw frame
     *        bytes must never be held as a plain PHP string this far down
     *        the pipeline (see App\Jobs\ServiceBle).
     */
    public function setCommand(string $commandBase64, int $cmd): self
    {
        $this->commandBase64 = $commandBase64;
        $this->cmd = $cmd;
        return $this;
    }

    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.ble',
            'id' => (string) random_int(100000000, 999999999),
            "params" => [
                "payload" => [
                    "data" => $this->commandBase64,
                    "cmd" => $this->cmd,
                ],
                "device" => [
                    "type" => $this->bluetoothDevice->bluetoothDeviceType(),
                    'mac' => $this->bluetoothDevice->mac,
                ],
                "timestamp" => time(),
            ],
            "version" => "1.0.0",
        ];
    }
}
