<?php

namespace App\Homeassistant;

use App\Helpers\HomeassistantHelper;
use App\Models\BluetoothDevice;
use Attribute;

/**
 * A momentary event (a detection, an error firing) rather than a
 * continuously-tracked state - HA surfaces it as an automation trigger, not
 * an entity. Discovery deliberately skips unique_id/name/state_topic (unlike
 * every other class here): per the MQTT Device Trigger spec there is no
 * entity to hold those, only the `device` this trigger belongs to and a
 * `topic`/`type`/`subtype` describing what fired. The trigger fires by
 * publishing *any* message (or one matching `payload`, if set) to `topic` -
 * the publish itself is the event, nothing is retained or polled.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class DeviceTrigger extends BaseEntity
{
    protected $entity = 'device_automation';

    public function __construct(
        public string $technicalName,
        public string $topic,
        public string $type,
        public string $subtype,
        public ?string $payload = null,
        public ?string $valueTemplate = null,
        public int $qos = 0,
    ) {
    }

    public function payload(): array
    {
        $definition = $this->device instanceof BluetoothDevice ? $this->device->device() : $this->device->definition();

        $config = [
            'automation_type' => 'trigger',
            'topic' => HomeassistantHelper::deviceTopic($this->device) . '/' . $this->topic,
            'type' => $this->type,
            'subtype' => $this->subtype,
            'qos' => $this->qos,
            'device' => [
                'ids' => [$this->device->serial_number],
                'name' => $this->device->name,
                'manufacturer' => 'Localkit',
                'model' => $definition->deviceName(),
                'sn' => $this->device->serial_number,
                'sw_version' => $this->device?->firmware ?? sprintf('MAC: %s', $this->device->mac ?? 'Unknown'),
            ],
        ];

        if ($this->payload !== null) {
            $config['payload'] = $this->payload;
        }

        if ($this->valueTemplate !== null) {
            $config['value_template'] = $this->valueTemplate;
        }

        return $config;
    }
}
