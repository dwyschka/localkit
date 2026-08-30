<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

/**
 * MQTT Vacuum's "state" schema is the odd one out among simple entities:
 * state_topic must carry a self-contained {"state": ..., "fan_speed": ...}
 * JSON payload - HA does not apply value_template to it, so it can't read
 * off the same "~" + value_template pattern every other entity here uses.
 * Whatever wires this up needs its own dedicated, actively-published state
 * topic (not just a value picked out of the existing ConfigurationInterface
 * blob) and needs to translate incoming plain-string commands (start,
 * pause, stop, return_to_base, clean_spot, locate - no JSON, no
 * command_template support per the MQTT vacuum spec) into whatever the
 * underlying device actually understands.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Vacuum extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'vacuum';
    protected $platform = 'vacuum';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic = 'vacuum/command',
        public string $stateTopic = 'vacuum/state',
        public string $icon = 'mdi:robot-vacuum',
        public ?string $valueTemplate = null,
        public array $supportedFeatures = ['start', 'stop', 'pause', 'status', 'clean_spot', 'locate'],
        public array $fanSpeedList = [],
        public ?string $entityCategory = null,
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = true,
    ) {
    }

    public function payload(): array
    {
        $config = parent::payload();
        unset($config['value_template']);

        $config['state_topic'] = $this->topic($this->stateTopic);
        $config['supported_features'] = $this->supportedFeatures;

        return $this->withExtra($config, [
            'fan_speed_list' => $this->fanSpeedList ?: null,
            'set_fan_speed_topic' => $this->fanSpeedList ? $this->topic($this->stateTopic . '_fan_speed') : null,
        ]);
    }
}
