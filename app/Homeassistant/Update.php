<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Update extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'update';
    protected $platform = 'update';

    public function __construct(
        public string $technicalName,
        public string $name,
        public ?string $stateTopic = null,
        public ?string $commandTopic = null,
        public string $icon = 'mdi:package-up',
        public ?string $valueTemplate = null,
        public string $deviceClass = 'firmware',
        public ?string $payloadInstall = null,
        public ?string $payloadAvailable = null,
        public ?string $payloadNotAvailable = null,
        public ?string $availabilityTemplate = null,
        public ?string $availabilityTopic = null,
        public ?string $entityCategory = 'diagnostic',
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = true,
    ) {
    }

    public function payload(): array
    {
        $config = parent::payload();

        return $this->withExtra($config, [
            'state_topic' => $this->topic($this->stateTopic),
            'payload_install' => $this->payloadInstall,
        ]);
    }
}
