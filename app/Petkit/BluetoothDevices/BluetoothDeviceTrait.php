<?php

namespace App\Petkit\BluetoothDevices;

trait BluetoothDeviceTrait
{
    public function toHomeassistant(): string
    {
        return json_encode($this->model->configuration()->toHomeassistant());
    }

}
