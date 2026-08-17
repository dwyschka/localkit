<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Valve extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'valve';
    protected $platform = 'valve';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:valve',
        public ?string $stateTopic = null,
        public ?string $valueTemplate = null,
        public ?string $deviceClass = null,
        public string $payloadOpen = 'OPEN',
        public string $payloadClose = 'CLOSE',
        public ?string $payloadStop = null,
        public string $stateOpen = 'open',
        public string $stateClosed = 'closed',
        public ?string $stateOpening = 'opening',
        public ?string $stateClosing = 'closing',
        public bool $reportsPosition = false,
        public int $positionOpen = 100,
        public int $positionClosed = 0,
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
        $config['payload_open'] = $this->payloadOpen;
        $config['payload_close'] = $this->payloadClose;
        $config['state_open'] = $this->stateOpen;
        $config['state_closed'] = $this->stateClosed;
        $config['reports_position'] = $this->reportsPosition;

        return $this->withExtra($config, [
            'state_topic' => $this->topic($this->stateTopic),
            'payload_stop' => $this->payloadStop,
            'state_opening' => $this->stateOpening,
            'state_closing' => $this->stateClosing,
            'position_open' => $this->reportsPosition ? $this->positionOpen : null,
            'position_closed' => $this->reportsPosition ? $this->positionClosed : null,
            'optimistic' => $this->optimistic ?: null,
        ]);
    }
}
