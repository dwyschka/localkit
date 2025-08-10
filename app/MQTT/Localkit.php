<?php

namespace App\MQTT;

use App\Models\Device;
use Illuminate\Support\Str;

class Localkit
{
    static protected $regEx = '/d_\w+_(\d{8}.?\d{5})/';
    static protected array $topics = [
        'localkit/clients'
    ];

    public static function isLocalkitTopic($topic): bool
    {
        return in_array($topic, self::$topics);
    }

    public static function route(string $topic, array $message)
    {
        switch ($topic) {
            case 'localkit/clients':
                self::setDeviceStatus($message);
        }
    }

    private static function setDeviceStatus(array $message)
    {
        $onlineDevices = [];
        foreach ($message as $device) {
            if(!Str::contains($device, '|timestamp=')) {
                continue;
            }

            $serialNumber = Str::of($device)->match(self::$regEx)->toString();
            $onlineDevices[] = $serialNumber;
        }

        if(!empty($onlineDevices)) {
            Device::where('serial_number', $onlineDevices)->update([
                'mqtt_connected' => true
            ]);
        }

        Device::whereNot('serial_number', $onlineDevices)->update([
            'mqtt_connected' => false
        ]);

    }
}
