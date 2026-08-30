<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Event extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'event';

    public function __construct(
        public string $technicalName,
        public string $name,
        public array $eventTypes,
        public string $stateTopic = 'event',
        public string $icon = 'mdi:calendar-alert',
        public ?string $deviceClass = null,
        public ?string $valueTemplate = null,
        public ?string $jsonAttributesTopic = null,
        public ?string $jsonAttributesTemplate = null,
        public ?string $payloadAvailable = null,
        public ?string $payloadNotAvailable = null,
        public ?string $availabilityTemplate = null,
        public ?string $availabilityTopic = null,
        public ?string $entityCategory = null,
        public ?string $uniqueId = null,
        public int $qos = 0,
    ) {
    }

    /**
     * An event, unlike every other entity here, only means anything at the
     * moment it's published - reusing the shared "~" topic (populated on
     * the ConfigurationInterface poll/update cycle, not when something
     * actually happens) would misrepresent both cadence and shape. Gets its
     * own dedicated, non-retained state topic instead (see whatever calls
     * publish() with this entity's resolved state_topic).
     */
    public function payload(): array
    {
        $config = parent::payload();
        $config['state_topic'] = $this->topic($this->stateTopic);
        $config['event_types'] = $this->eventTypes;

        return $this->withExtra($config, [
            'json_attributes_topic' => $this->topic($this->jsonAttributesTopic),
            'json_attributes_template' => $this->jsonAttributesTemplate,
        ]);
    }
}
