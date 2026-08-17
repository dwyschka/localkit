<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\DiscernMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class PublishDiscernUpdate implements ShouldQueue
{
    use Queueable;

    /**
     * Device types with a pet_discern/post MQTT handler (see
     * DevDiscernPicController's docblock - confirmed on both D4SH and W7H).
     */
    public const DISCERN_CAPABLE_DEVICE_TYPES = ['d4sh', 'w7h'];

    public function __construct(protected Device $device)
    {
    }

    public function handle(): void
    {
        $message = DiscernMessage::send($this->device);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }

    /**
     * Dispatched whenever a pet gets a new photo (see Pet::syncImages()) so
     * every discern-capable device re-fetches dev_discern_pic instead of
     * waiting for its own periodic refresh.
     */
    public static function dispatchForAllDevices(): void
    {
        Device::query()
            ->whereIn('device_type', self::DISCERN_CAPABLE_DEVICE_TYPES)
            ->each(fn (Device $device) => self::dispatch($device));
    }
}
