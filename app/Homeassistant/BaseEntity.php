<?php

namespace App\Homeassistant;

use App\Helpers\HomeassistantHelper;

class BaseEntity
{

    protected \App\Models\Device $device;

    public function toTopic(): string
    {
        return HomeassistantHelper::configTopic($this->technicalName(), $this->entity(), $this->device);
    }

    private function technicalName()
    {
        return $this->technicalName;
    }

    private function entity()
    {
        return $this->entity;
    }

    public function toPayload(): string
    {
        return json_encode($this->payload());
    }

    public function payload(): array
    {
        $uniqueId = sprintf('%s_%s', $this->device->serial_number, $this->technicalName());

        $config = [
            'name' => $this->name,
            'state_topic' => '~',
            'unique_id' => $uniqueId,
            'dev' => [
                'ids' => [$this->device->serial_number],
                'name' => $this->device->name,
                'manufacturer' => 'Localkit',
                'model' => $this->device->definition()->deviceName(),
            ],
            '~' => HomeassistantHelper::deviceTopic($this->device),
            'payload_available' => 'online',
            'payload_not_available' => 'offline',
            'availability_template' => 'online',
            'availability_topic' => '~',
        ];



        if (isset($this->commandTopic)) {
            $config['command_topic'] = HomeassistantHelper::deviceTopic($this->device) . '/' . $this->commandTopic;
        }
        if (isset($this->commandTemplate)) {
            $config['command_template'] = $this->commandTemplate;
        }
        if (isset($this->options)) {
            $config['options'] = $this->options;
        }
        if (isset($this->payloadOff)) {
            $config['payload_off'] = $this->payloadOff;
        }
        if (isset($this->payloadOn)) {
            $config['payload_on'] = $this->payloadOn;
        }
        // Add optional fields if set
        if (isset($this->deviceClass)) {
            $config['device_class'] = $this->deviceClass;
        }

        if (isset($this->platform)) {
            $config['platform'] = $this->platform;
        }

        if (isset($this->stateClass)) {
            $config['state_class'] = $this->stateClass;
        }

        if (isset($this->icon)) {
            $config['icon'] = $this->icon;
        }

        if (isset($this->icon)) {
            $config['icon'] = $this->icon;
        }

        if (isset($this->stateOn)) {
            $config['state_on'] = $this->stateOn;
        }

        if (isset($this->stateOff)) {
            $config['state_off'] = $this->stateOff;
        }

        if (isset($this->payloadAvailable)) {
            $config['payload_available'] = $this->payloadAvailable;
        }
        if (isset($this->payloadNotAvailable)) {
            $config['payload_not_available'] = $this->payloadNotAvailable;
        }
        if (isset($this->availabilityTopic)) {
            $config['availability_topic'] = $this->availabilityTopic;
        }
        if (isset($this->icon)) {
            $config['icon'] = $this->icon;
        }
        if (isset($this->availabilityTemplate)) {
            $config['availability_template'] = $this->availabilityTemplate;
        }
        if ($this->valueTemplate !== null) {
            $config['value_template'] = $this->valueTemplate;
        }
        if (isset($this->entityCategory)) {
            $config['entity_category'] = $this->entityCategory;
        }
        if (isset($this->min)) {
            $config['min'] = $this->min;
        }
        if (isset($this->max)) {
            $config['max'] = $this->max;
        }
        if (isset($this->step)) {
            $config['step'] = $this->step;
        }

        if (isset($this->unitOfMeasurement)) {
            $config['unit_of_measurement'] = $this->unitOfMeasurement;
        }

        return $config;
    }

    public function setDevice(\App\Models\Device $device): void
    {

        $this->device = $device;
    }
}
