<?php

namespace App\Petkit\BluetoothDevices;

/**
 * A proxy whose `ble_relay_over` events are passed to RelayConnection, so
 * writes through it can wait until it's connected to the peripheral.
 */
interface ReportsBleRelayInterface extends BluetoothProxyInterface
{
}
