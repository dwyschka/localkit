<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Humidifier extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'humidifier';
    protected $platform = 'humidifier';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:air-humidifier',
        public string $deviceClass = 'humidifier',
        public ?string $valueTemplate = null,
        public ?string $targetHumidityCommandTopic = null,
        public ?string $targetHumidityStateTopic = null,
        public int $minHumidity = 0,
        public int $maxHumidity = 100,
        public ?string $modeCommandTopic = null,
        public ?string $modeStateTopic = null,
        public array $modes = [],
        public string $payloadOn = 'ON',
        public string $payloadOff = 'OFF',
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
            'target_humidity_command_topic' => $this->topic($this->targetHumidityCommandTopic),
            'target_humidity_state_topic' => $this->topic($this->targetHumidityStateTopic),
            'min_humidity' => $this->targetHumidityCommandTopic ? $this->minHumidity : null,
            'max_humidity' => $this->targetHumidityCommandTopic ? $this->maxHumidity : null,
            'mode_command_topic' => $this->topic($this->modeCommandTopic),
            'mode_state_topic' => $this->topic($this->modeStateTopic),
            'modes' => $this->modes,
        ]);
    }
}
