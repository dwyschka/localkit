<?php

namespace App\Petkit\BluetoothDevices;

use App\Models\BluetoothDevice;
use Illuminate\Support\Facades\Log;

class Message
{

    public static function handleProxyMessage(\stdClass $message)
    {
        Log::info('BLE Message Received', ['msg' => $message]);
        $btDevice = BluetoothDevice::where('mac', $message->device->mac)->first();

        if(!$btDevice) {
            Log::info('No BTDevice Found', ['msg' => $message]);
            return;
        }
        if(!($btDevice->device() instanceof HasParserInterface)) {
            Log::info('BTDevice does not implement HasParserInterface', ['msg' => $message]);
            return;
        }

        $btDevice->device()->handleMessage($message->payload[0]);
    }
}
