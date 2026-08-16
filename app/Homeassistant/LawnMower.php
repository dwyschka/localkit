<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class LawnMower extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'lawn_mower';
    protected $platform = 'lawn_mower';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $icon = 'mdi:robot-mower',
        public ?string $valueTemplate = null,
        public ?string $activityStateTopic = null,
        public ?string $activityValueTemplate = null,
        public ?string $dockCommandTopic = null,
        public ?string $startMowingCommandTopic = null,
        public ?string $pauseCommandTopic = null,
        public array $supportedFeatures = ['start_mowing', 'pause', 'dock'],
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
        $config['supported_features'] = $this->supportedFeatures;

        return $this->withExtra($config, [
            'activity_state_topic' => $this->topic($this->activityStateTopic),
            'activity_value_template' => $this->activityValueTemplate,
            'dock_command_topic' => $this->topic($this->dockCommandTopic),
            'start_mowing_command_topic' => $this->topic($this->startMowingCommandTopic),
            'pause_command_topic' => $this->topic($this->pauseCommandTopic),
        ]);
    }
}
