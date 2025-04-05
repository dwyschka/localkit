<?php

namespace App\Homeassistant;

use App\Helpers\HomeassistantHelper;
use Illuminate\Support\Collection;
use ReflectionClass;

class HomeassistantTopicService
{

    public function __construct(protected Collection $devices)
    {

    }

    public function resolve(string $topic, \stdClass $message)
    {
        /** @var \App\Models\Device $device */
        foreach($this->devices as $device) {
            $reflection = new ReflectionClass($device->definition());
            $methods = $reflection->getMethods();

            foreach ($methods as $method) {
                $attributes = $method->getAttributes(HomeassistantTopic::class);

                if (empty($attributes)) {
                    continue;
                }

                foreach ($attributes as $attribute) {
                    /** @var HomeassistantTopic $instance */
                    $instance = $attribute->newInstance();
                    if ($instance->getTopic($device) == $topic) {
                        dump($topic);
                        $device->definition()->{$method->getName()}($message);
                    }

                }

            }
        }
    }
}
