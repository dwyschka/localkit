<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Siren extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'siren';
    protected $platform = 'siren';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:bullhorn',
        public ?string $stateTopic = null,
        public ?string $valueTemplate = null,
        public ?string $commandTemplate = null,
        public string $payloadOn = 'ON',
        public string $payloadOff = 'OFF',
        public mixed $stateOn = null,
        public mixed $stateOff = null,
        public array $availableTones = [],
        public bool $supportDuration = false,
        public bool $supportVolumeSet = false,
        public bool $optimistic = false,
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

        return $this->withExtra($config, [
            'state_topic' => $this->topic($this->stateTopic),
            'available_tones' => $this->availableTones,
            'support_duration' => $this->supportDuration ?: null,
            'support_volume_set' => $this->supportVolumeSet ?: null,
            'optimistic' => $this->optimistic ?: null,
        ]);
    }
}
