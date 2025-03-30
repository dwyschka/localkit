<?php

namespace App\Homeassistant;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class BinarySensor extends BaseEntity
{
    public function __construct(
        public string $name,
        public string $deviceClass = '',
        public string $stateClass = '',
        public string $icon = '',
        public ?string $valueTemplate = null,
        public string $payloadOn = 'ON',
        public string $payloadOff = 'OFF',
        public bool $availabilityMode = true,
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = false
    ) {
    }
}
