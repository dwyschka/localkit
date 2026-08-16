<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Cover extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'cover';
    protected $platform = 'cover';

    public function __construct(
        public string $technicalName,
        public string $name,
        public ?string $commandTopic = null,
        public ?string $stateTopic = null,
        public ?string $positionTopic = null,
        public ?string $setPositionTopic = null,
        public string $icon = 'mdi:garage',
        public ?string $deviceClass = null,
        public string $payloadOpen = 'OPEN',
        public string $payloadClose = 'CLOSE',
        public string $payloadStop = 'STOP',
        public string $stateOpen = 'open',
        public string $stateClosed = 'closed',
        public string $stateOpening = 'opening',
        public string $stateClosing = 'closing',
        public int $positionOpen = 100,
        public int $positionClosed = 0,
        public bool $optimistic = false,
        public ?string $valueTemplate = null,
        public ?string $positionTemplate = null,
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
        $config['payload_open'] = $this->payloadOpen;
        $config['payload_close'] = $this->payloadClose;
        $config['payload_stop'] = $this->payloadStop;
        $config['state_open'] = $this->stateOpen;
        $config['state_closed'] = $this->stateClosed;
        $config['state_opening'] = $this->stateOpening;
        $config['state_closing'] = $this->stateClosing;
        $config['position_open'] = $this->positionOpen;
        $config['position_closed'] = $this->positionClosed;

        return $this->withExtra($config, [
            'state_topic' => $this->topic($this->stateTopic),
            'position_topic' => $this->topic($this->positionTopic),
            'set_position_topic' => $this->topic($this->setPositionTopic),
            'position_template' => $this->positionTemplate,
            'optimistic' => $this->optimistic ?: null,
        ]);
    }
}
