<?php

namespace App\Petkit\Devices\Configuration;


interface ConfigurationInterface
{

    public function getDevice();
    public function toArray(): array;

}
