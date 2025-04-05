<?php

namespace App\Homeassistant;

use App\Petkit\Devices\Configuration\ConfigurationInterface;
use PhpMqtt\Client\MqttClient;

class AutoDiscoveryService
{

    public function __construct(protected MqttClient $mqttClient)
    {
    }

    public function discover(ConfigurationInterface $configuration)
    {
        $reflectionClass = new \ReflectionClass($configuration);

        $properties = $reflectionClass->getProperties();
        foreach ($properties as $property) {
            $propertyAttributes = $property->getAttributes();
            if(empty($propertyAttributes)) {
                continue;
            }
            /** @var \ReflectionAttribute $attr */
            foreach($propertyAttributes as $attr) {
                $instance = $attr->newInstance();

                if(!($instance instanceof BaseEntity)) {
                    continue;
                }

                    $instance->setDevice($configuration->getDevice());

                $this->mqttClient->publish($instance->toTopic(), $instance->toPayload());
            }
        }

    }


}
