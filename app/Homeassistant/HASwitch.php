<?php

namespace App\Homeassistant;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class HASwitch extends BaseEntity
{
    protected $entity = 'switch';
    protected $platform = 'switch';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:toggle-switch',
        public ?string $valueTemplate = null,
        public ?string $commandTemplate = null,
        public string|int|bool $payloadOn = 'ON',
        public string|int|bool $payloadOff = 'OFF',
        public ?string $payloadAvailable = null,
        public mixed $stateOn = null,
        public mixed $stateOff = null,
        public ?string $payloadNotAvailable = null,
        public ?string $availabilityTemplate = null,
        public ?string $availabilityTopic = null,
        public ?string $entityCategory = null,
        public bool $optimistic = false,
        public bool $availabilityMode = true,
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = false,
        public string $deviceClass = 'switch'
    ) {

    }


}
