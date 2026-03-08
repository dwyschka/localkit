<?php

namespace App\Petkit\BluetoothDevices;

interface HasParserInterface
{

    public function handleMessage(\stdClass $message): bool;

}
