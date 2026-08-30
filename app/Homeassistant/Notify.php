<?php

namespace App\Homeassistant;

use Attribute;

/**
 * Stateless "send only" entity - no state_topic, no on/off, just a
 * command_topic that receives an arbitrary notification payload.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Notify extends BaseEntity
{
    protected $entity = 'notify';
    protected $platform = 'notify';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:message-text',
        public ?string $valueTemplate = null,
        public ?string $commandTemplate = null,
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
