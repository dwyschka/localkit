<?php

namespace App\Petkit\BluetoothDevices;

use stdClass;

interface HasParserInterface
{

    public function handleMessage(stdClass $message): bool;

}
