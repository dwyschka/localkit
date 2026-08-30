<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

/**
 * Two mutually exclusive schemas share this one platform: "emitter" (sends
 * IR signals via commandTopic/commandTemplate) or "receiver" (reports
 * captured signals via stateTopic/valueTemplate) - set $schema to pick
 * which topic pair actually gets used.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Infrared extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'infrared';
    protected $platform = 'infrared';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $schema = 'emitter',
        public ?string $commandTopic = null,
        public ?string $commandTemplate = null,
        public ?string $stateTopic = null,
        public ?string $valueTemplate = null,
        public string $icon = 'mdi:remote',
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
        $config['schema'] = $this->schema;

        return $this->withExtra($config, [
            'state_topic' => $this->topic($this->stateTopic),
        ]);
    }
}
