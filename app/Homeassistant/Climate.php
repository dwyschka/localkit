<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Climate extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'climate';
    protected $platform = 'climate';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $icon = 'mdi:thermostat',
        public ?string $modeCommandTopic = null,
        public ?string $modeStateTopic = null,
        public array $modes = ['off', 'heat'],
        public ?string $temperatureCommandTopic = null,
        public ?string $temperatureStateTopic = null,
        public ?string $currentTemperatureTopic = null,
        public ?string $actionTopic = null,
        public float $minTemp = 7.0,
        public float $maxTemp = 35.0,
        public float $tempStep = 0.5,
        public ?string $temperatureUnit = null,
        public ?string $valueTemplate = null,
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
        $config['temp_step'] = $this->tempStep;

        return $this->withExtra($config, [
            'mode_command_topic' => $this->topic($this->modeCommandTopic),
            'mode_state_topic' => $this->topic($this->modeStateTopic),
            'temperature_command_topic' => $this->topic($this->temperatureCommandTopic),
            'temperature_state_topic' => $this->topic($this->temperatureStateTopic),
            'current_temperature_topic' => $this->topic($this->currentTemperatureTopic),
            'action_topic' => $this->topic($this->actionTopic),
            'temperature_unit' => $this->temperatureUnit,
        ]);
    }
}
