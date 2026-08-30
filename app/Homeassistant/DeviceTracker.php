<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class DeviceTracker extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'device_tracker';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $sourceType = 'gps',
        public string $payloadHome = 'home',
        public string $payloadNotHome = 'not_home',
        public ?string $payloadReset = null,
        public ?string $valueTemplate = null,
        public ?string $jsonAttributesTopic = null,
        public ?string $entityCategory = null,
        public ?string $uniqueId = null,
        public int $qos = 0,
    ) {
    }

    public function payload(): array
    {
        $config = parent::payload();
        $config['source_type'] = $this->sourceType;
        $config['payload_home'] = $this->payloadHome;
        $config['payload_not_home'] = $this->payloadNotHome;

        return $this->withExtra($config, [
            'payload_reset' => $this->payloadReset,
            'json_attributes_topic' => $this->topic($this->jsonAttributesTopic),
        ]);
    }
}
