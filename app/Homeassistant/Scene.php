<?php

namespace App\Homeassistant;

use Attribute;

/**
 * Stateless "activate" entity - a single button-like action, no state_topic.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Scene extends BaseEntity
{
    protected $entity = 'scene';
    protected $platform = 'scene';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:palette',
        public ?string $valueTemplate = null,
        public string|int $payloadOn = 'ON',
        public ?string $payloadAvailable = null,
        public ?string $payloadNotAvailable = null,
        public ?string $availabilityTemplate = null,
        public ?string $availabilityTopic = null,
        public ?string $entityCategory = null,
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = false,
    ) {
    }

    public function payload(): array
    {
        $config = parent::payload();
        unset($config['state_topic']);

        return $config;
    }
}
