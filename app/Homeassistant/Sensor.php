<?php

namespace App\Homeassistant;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Sensor extends BaseEntity
{
    protected $entity = 'sensor';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $icon = 'mdi:text',
        public ?string $stateClass = null,
        public ?string $deviceClass = null,
        public ?string $unitOfMeasurement = null,
        public ?string $valueTemplate = null,
        public ?string $entityCategory = null,
        public bool $availabilityMode = true,
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = false,
        public ?string $encoding = 'utf-8',
        public int $expiryAfter = 0
    ) {

    }


}
