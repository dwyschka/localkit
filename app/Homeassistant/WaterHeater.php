<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class WaterHeater extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'water_heater';
    protected $platform = 'water_heater';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $icon = 'mdi:water-boiler',
        public ?string $valueTemplate = null,
        public ?string $modeCommandTopic = null,
        public ?string $modeStateTopic = null,
        public array $modes = ['off', 'eco', 'performance'],
        public ?string $temperatureCommandTopic = null,
        public ?string $temperatureStateTopic = null,
        public ?string $currentTemperatureTopic = null,
        public float $minTemp = 110,
        public float $maxTemp = 140,
        public ?string $temperatureUnit = null,
        public ?string $powerCommandTopic = null,
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
        $config['modes'] = $this->modes;
        $config['min_temp'] = $this->minTemp;
        $config['max_temp'] = $this->maxTemp;

        return $this->withExtra($config, [
            'mode_command_topic' => $this->topic($this->modeCommandTopic),
            'mode_state_topic' => $this->topic($this->modeStateTopic),
            'temperature_command_topic' => $this->topic($this->temperatureCommandTopic),
            'temperature_state_topic' => $this->topic($this->temperatureStateTopic),
            'current_temperature_topic' => $this->topic($this->currentTemperatureTopic),
            'power_command_topic' => $this->topic($this->powerCommandTopic),
            'temperature_unit' => $this->temperatureUnit,
        ]);
    }
}
