<?php

namespace App\Homeassistant;

use stdClass;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Helpers\HomeassistantHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

class HomeassistantTopicService
{

    public function __construct(protected Collection $devices)
    {

    }

    public function resolve(string $topic, stdClass $message)
    {
        /** @var Device|BluetoothDevice $device */
        foreach($this->devices as $device) {
            $definition = $device instanceof BluetoothDevice ? $device->device() : $device->definition();

            $reflection = new ReflectionClass($definition);
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

                        $definition->{$method->getName()}($message);

                    }

                }

            }
        }
    }
}
