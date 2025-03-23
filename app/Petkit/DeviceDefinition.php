<?php

namespace App\Petkit;

use App\Models\Device;

interface DeviceDefinition
{

    public function hasAction(string $action): bool;
    public function stateTopics(): array;
    public function subscribedTopics(): array;

    public function propertyChange(Device $device): void;
}
