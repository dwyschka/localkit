<?php

namespace App\Homeassistant;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Image extends BaseEntity
{
    protected $entity = 'image';
    protected $platform = 'image';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $icon = 'mdi:cat',
        public ?string $valueTemplate = null,
        public ?string $commandTemplate = null,
        public ?string $entityCategory = null,
        public bool $optimistic = false,
        public bool $availabilityMode = true,
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = false,
        public string $imageTopic = '/snapshot',
        public string $imageEncoding = 'b64'
        

    ) {

    }


}
