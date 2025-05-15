<?php

namespace App\Homeassistant;

use App\Helpers\HomeassistantHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
                        Log::info('HomeassistantTopicService', [
                            'device' => $device->deviceName(),
                            'method' => $method->getName(),
                            'message' => $message,
                        ]);
                        $device->definition()->{$method->getName()}($message);
                    }

                }

            }
        }
    }
}
