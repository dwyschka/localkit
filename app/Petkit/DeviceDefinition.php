<?php

namespace App\Petkit;

use App\Models\BluetoothDevice;
use App\Models\Device;

interface DeviceDefinition
{
    /**
     * How many hopper amounts this device's feed schedule carries per item -
     * 1 (wire key 'a') for every feeder except D4SH, which is 2 ('a1'/'a2').
     * schedule.md §4e/§4f is the single source of truth for this split;
     * HandlesFeederSchedule reads this constant instead of any class
     * hard-coding 'a1'/'a2' or an `instanceof` check on a specific device.
     * Non-feeder devices (litter boxes, fountains) don't use this constant
     * but inherit the default so it's never undefined.
     */
    public const FEEDER_COUNT = 1;

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
