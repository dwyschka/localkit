<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Text extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'text';
    protected $platform = 'text';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:form-textbox',
        public ?string $stateTopic = null,
        public ?string $valueTemplate = null,
        public ?string $commandTemplate = null,
        public int $min = 0,
        public int $max = 255,
        public ?string $pattern = null,
        public string $mode = 'text',
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
        $config['mode'] = $this->mode;

        return $this->withExtra($config, [
            'state_topic' => $this->topic($this->stateTopic),
            'pattern' => $this->pattern,
        ]);
    }
}
