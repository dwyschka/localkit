<?php

namespace App\Homeassistant;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Select extends BaseEntity
{
    protected $entity = 'select';
    protected $platform = 'select';

    public function __construct(
        public string $technicalName,
        public string $name,
        public array $options = [],
        public string $commandTopic,
        public string $icon = 'mdi:form-select',
        public ?string $valueTemplate = null,
        public ?string $commandTemplate = null,
        public ?string $entityCategory = null,
        public bool $optimistic = false,
        public bool $availabilityMode = true,
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = false
    ) {

    }


}
