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

    public function toPayload(): string {
        return json_encode($this->payload());
    }
    public function payload(): array {
        $uniqueId = sprintf('%s_%s', $this->device->serial_number, $this->technicalName());

        $config = [
            'name' => $this->name,
            'state_topic' => '~',
            'unique_id' => $uniqueId,
            'dev' => [
                'ids' => [$this->device->serial_number],
                'name' =>$this->device->name,
                'manufacturer' => 'Localkit',
                'model' => $this->device->definition()->deviceName(),
            ],
            '~' => HomeassistantHelper::deviceTopic($this->device),

        ];

        if(!empty($attribute->payloadOn)) {
            $config['payload_on'] = $this->payloadOn;
        }
        if(!empty($attribute->payloadOff)) {
            $config['payload_off'] = $this->payloadOff;
        }
        // Add optional fields if set
        if (!empty($attribute->deviceClass)) {
            $config['device_class'] = $this->deviceClass;
        }

        if (!empty($attribute->stateClass)) {
            $config['state_class'] = $this->stateClass;
        }

        if (!empty($attribute->icon)) {
            $config['icon'] = $this->icon;
        }

        if ($this->valueTemplate !== null) {
            $config['value_template'] = $this->valueTemplate;
        }


        return $config;
    }

    public function setDevice(\App\Models\Device $device): void  {

        $this->device = $device;
    }

    private function technicalName()
    {
       return $this->technicalName;
    }

    private function entity()
    {
        return $this->entity;
    }
}
