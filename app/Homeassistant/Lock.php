<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Lock extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'lock';
    protected $platform = 'lock';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:lock',
        public ?string $valueTemplate = null,
        public ?string $commandTemplate = null,
        public string $payloadLock = 'LOCK',
        public string $payloadUnlock = 'UNLOCK',
        public ?string $payloadOpen = null,
        public string $stateLocked = 'LOCKED',
        public string $stateUnlocked = 'UNLOCKED',
        public ?string $stateLocking = null,
        public ?string $stateUnlocking = null,
        public ?string $stateJammed = null,
        public ?string $payloadAvailable = null,
        public ?string $payloadNotAvailable = null,
        public ?string $availabilityTemplate = null,
        public ?string $availabilityTopic = null,
        public ?string $entityCategory = null,
        public bool $optimistic = false,
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = false,
    ) {
    }

    public function payload(): array
    {
        $config = parent::payload();
        $config['payload_lock'] = $this->payloadLock;
        $config['payload_unlock'] = $this->payloadUnlock;
        $config['state_locked'] = $this->stateLocked;
        $config['state_unlocked'] = $this->stateUnlocked;

        return $this->withExtra($config, [
            'payload_open' => $this->payloadOpen,
            'state_locking' => $this->stateLocking,
            'state_unlocking' => $this->stateUnlocking,
            'state_jammed' => $this->stateJammed,
            'optimistic' => $this->optimistic ?: null,
        ]);
    }
}
