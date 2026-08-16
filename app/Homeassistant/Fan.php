<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Fan extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'fan';
    protected $platform = 'fan';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:fan',
        public ?string $valueTemplate = null,
        public ?string $stateValueTemplate = null,
        public string $payloadOn = 'ON',
        public string $payloadOff = 'OFF',
        public ?string $oscillationCommandTopic = null,
        public ?string $oscillationStateTopic = null,
        public ?string $percentageCommandTopic = null,
        public ?string $percentageStateTopic = null,
        public int $speedRangeMin = 1,
        public int $speedRangeMax = 100,
        public ?string $presetModeCommandTopic = null,
        public ?string $presetModeStateTopic = null,
        public array $presetModes = [],
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
            'state_value_template' => $this->stateValueTemplate,
            'oscillation_command_topic' => $this->topic($this->oscillationCommandTopic),
            'oscillation_state_topic' => $this->topic($this->oscillationStateTopic),
            'percentage_command_topic' => $this->topic($this->percentageCommandTopic),
            'percentage_state_topic' => $this->topic($this->percentageStateTopic),
            'speed_range_min' => $this->percentageCommandTopic ? $this->speedRangeMin : null,
            'speed_range_max' => $this->percentageCommandTopic ? $this->speedRangeMax : null,
            'preset_mode_command_topic' => $this->topic($this->presetModeCommandTopic),
            'preset_mode_state_topic' => $this->topic($this->presetModeStateTopic),
            'preset_modes' => $this->presetModes,
            'optimistic' => $this->optimistic ?: null,
        ]);
    }
}
