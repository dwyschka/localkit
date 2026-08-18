<?php

namespace App\Petkit\BluetoothDevices;

use App\Models\BluetoothDevice;

interface BluetoothProxyInterface
{

    public function btConnect(BluetoothDevice $btDevice): void;

    public function btWrite(BluetoothDevice $btDevice, string $rawCommand, int $cmd): void;
}
