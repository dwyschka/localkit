<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class DateTime extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'datetime';
    protected $platform = 'datetime';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:calendar-clock',
        public ?string $stateTopic = null,
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
        return $this->withExtra(parent::payload(), [
            'state_topic' => $this->topic($this->stateTopic),
        ]);
    }
}
