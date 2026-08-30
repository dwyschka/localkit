<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class Light extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'light';
    protected $platform = 'light';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:lightbulb',
        public ?string $stateTopic = null,
        public ?string $valueTemplate = null,
        public ?string $stateValueTemplate = null,
        public string $payloadOn = 'ON',
        public string $payloadOff = 'OFF',
        public ?string $brightnessCommandTopic = null,
        public ?string $brightnessStateTopic = null,
        public int $brightnessScale = 255,
        public ?string $rgbCommandTopic = null,
        public ?string $rgbStateTopic = null,
        public ?string $colorTempCommandTopic = null,
        public ?string $colorTempStateTopic = null,
        public bool $colorTempKelvin = false,
        public ?string $effectCommandTopic = null,
        public ?string $effectStateTopic = null,
        public array $effectList = [],
        public bool $optimistic = false,
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

        return $this->withExtra($config, [
            'state_topic' => $this->topic($this->stateTopic),
            'state_value_template' => $this->stateValueTemplate,
            'brightness_command_topic' => $this->topic($this->brightnessCommandTopic),
            'brightness_state_topic' => $this->topic($this->brightnessStateTopic),
            'brightness_scale' => $this->brightnessCommandTopic ? $this->brightnessScale : null,
            'rgb_command_topic' => $this->topic($this->rgbCommandTopic),
            'rgb_state_topic' => $this->topic($this->rgbStateTopic),
            'color_temp_command_topic' => $this->topic($this->colorTempCommandTopic),
            'color_temp_state_topic' => $this->topic($this->colorTempStateTopic),
            'color_temp_kelvin' => $this->colorTempKelvin ?: null,
            'effect_command_topic' => $this->topic($this->effectCommandTopic),
            'effect_state_topic' => $this->topic($this->effectStateTopic),
            'effect_list' => $this->effectList,
            'optimistic' => $this->optimistic ?: null,
        ]);
    }
}
