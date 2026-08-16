<?php

namespace App\Homeassistant;

use App\Helpers\HomeassistantHelper;
use App\Models\BluetoothDevice;
use Attribute;

/**
 * NFC/RFID tag scan - same "no entity" shape as DeviceTrigger. HA fires its
 * own `tag_scanned` event (usable in automations) whenever anything is
 * published to `topic`, rather than tracking on/off state, so there's no
 * unique_id/name/state_topic here either.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Tag extends BaseEntity
{
    protected $entity = 'tag';

    public function __construct(
        public string $technicalName,
        public string $topic,
        public ?string $valueTemplate = null,
    ) {
    }

    public function payload(): array
    {
        $definition = $this->device instanceof BluetoothDevice ? $this->device->device() : $this->device->definition();

        $config = [
            'topic' => HomeassistantHelper::deviceTopic($this->device) . '/' . $this->topic,
            'device' => [
                'ids' => [$this->device->serial_number],
                'name' => $this->device->name,
                'manufacturer' => 'Localkit',
                'model' => $definition->deviceName(),
                'sn' => $this->device->serial_number,
                'sw_version' => $this->device?->firmware ?? sprintf('MAC: %s', $this->device->mac ?? 'Unknown'),
            ],
        ];

        if ($this->valueTemplate !== null) {
            $config['value_template'] = $this->valueTemplate;
        }

        return $config;
    }
}
