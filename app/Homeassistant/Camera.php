<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

/**
 * Unlike Image (base64 payload over the shared "~" topic), MQTT Camera
 * expects raw binary image bytes on a dedicated topic - no image_encoding
 * key, no state_topic.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Camera extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'camera';
    protected $platform = 'camera';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $topicSuffix = 'camera',
        public string $icon = 'mdi:camera',
        public ?string $valueTemplate = null,
        public ?string $entityCategory = null,
        public ?string $uniqueId = null,
        public int $qos = 0,
    ) {
    }

    public function payload(): array
    {
        $config = parent::payload();
        unset($config['state_topic'], $config['image_topic'], $config['image_encoding']);

        $config['topic'] = $this->topic($this->topicSuffix);

        return $config;
    }
}
