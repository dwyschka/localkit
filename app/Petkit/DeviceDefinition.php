<?php

namespace App\Petkit;

use App\Models\BluetoothDevice;
use App\Models\Device;

interface DeviceDefinition
{
    public function hasAction(string $action): bool;
    public function stateTopics(): array;
    public function subscribedTopics(): array;

    public function propertyChange(Device $device): void;

    /**
     * Pure defaults for this device type's Configuration DTO - none of the
     * current record's stored values, just what a fresh device would get.
     * Callers are expected to write this straight over the record's
     * 'configuration' (not merge it), so it fully replaces whatever's
     * there now - including any corrupted/stale nested state (e.g. an
     * uninitialized MultiRangeDTO 'name').
     */
    public function resetConfiguration(): array;
}
