<?php

namespace App\MQTT;

use App\Helpers\PetkitHeader;
use App\Http\Resources\MQTT\DevBLEDevice;
use App\Http\Resources\MQTT\DevDeviceInfo;
use App\Http\Resources\MQTT\DevMultiConfig;
use App\Http\Resources\MQTT\DevScheduleGet;
use App\Http\Resources\MQTT\DevServerInfo;
use App\Http\Resources\MQTT\StateReport;
use App\Models\Device;
use Illuminate\Http\Resources\Json\JsonResource;

class UserGet
{

    public static function replyToState(string $deviceId, string $deviceName, $message): AnswerDTO {

        $message = self::toStateReply($message);

        return new AnswerDTO(
            topic: sprintf('/%s/%s/user/get', $deviceId, $deviceName),
            message: $message,
        );
    }

    public static function reply(string $deviceId, string $deviceName, $message): ?AnswerDTO {

        switch ($message?->params?->dataType ?? null) {
            case 'dev_device_info':
                $message = self::toDevDeviceInfo($message);
                break;
            case 'dev_schedule_get':
                $message = self::toDevScheduleGet($message);
                break;
            case 'dev_ble_device':
                $message = self::toDevBLEDevice($message);
                break;
            case 'dev_multi_config':
                $message = self::toDevMultiConfig($message);
                break;
            case 'dev_serverinfo':
                $message = self::toDevServerInfo($message);
                break;

        }
        return new AnswerDTO(
            topic: sprintf('/%s/%s/user/get', $deviceId, $deviceName),
            message: $message,
        );
    }

    private static function toDevDeviceInfo($message): JsonResource
    {
        $head = $message->params->XDevice;
        $deviceId = PetkitHeader::petkitId($head);
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        return DevDeviceInfo::make($device);
    }

    private static function toDevScheduleGet($message)
    {
        $head = $message->params->XDevice;
        $deviceId = PetkitHeader::petkitId($head);
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        return DevScheduleGet::make($device);
    }

    private static function toDevBLEDevice($message)
    {
        $head = $message->params->XDevice;
        $deviceId = PetkitHeader::petkitId($head);
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        return DevBLEDevice::make($device);
    }

    private static function toDevMultiConfig($message)
    {
        $head = $message->params->XDevice;
        $deviceId = PetkitHeader::petkitId($head);
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        return DevMultiConfig::make($device);
    }

    private static function toDevServerInfo($message)
    {
        $head = $message->params->XDevice;
        $deviceId = PetkitHeader::petkitId($head);
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        return DevServerInfo::make($device);
    }

    private static function toStateReply($message)
    {
        $head = $message->params->XDevice;
        $deviceId = PetkitHeader::petkitId($head);
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        return StateReport::make($device);
    }
}
