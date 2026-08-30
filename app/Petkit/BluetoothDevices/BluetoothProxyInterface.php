<?php

namespace App\Petkit\BluetoothDevices;

use App\Models\BluetoothDevice;

interface BluetoothProxyInterface
{

    public function btConnect(BluetoothDevice $btDevice): void;

    /**
     * @param string $commandBase64 Base64-encoded raw BLE command frame -
     *        callers must encode before calling, never pass raw binary
     *        (it isn't valid UTF-8 and breaks queue payload serialization).
     */
    public function btWrite(BluetoothDevice $btDevice, string $commandBase64, int $cmd): void;
}
