<?php

namespace App\Homeassistant;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Button extends BaseEntity
{
    protected $entity = 'button';
    protected $platform = 'button';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:form-select',
        public ?string $valueTemplate = null,
        public ?string $commandTemplate = null,
        public string|int $payloadOn = 'ON',
        public string|int $payloadOff = 'OFF',
        public ?string $payloadAvailable = null,
        public ?string $stateOn = null,
        public ?string $stateOff = null,
        public ?string $payloadNotAvailable = null,
        public ?string $availabilityTemplate = null,
        public ?string $availabilityTopic = null,
        public ?string $entityCategory = null,
        public bool $optimistic = false,
        public bool $availabilityMode = true,
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = false,
    ) {

    }


}
