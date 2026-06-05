<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\RebootMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class Reboot implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Device $device)
    {
    }

    public function handle(): void
    {
        $message = RebootMessage::send($this->device);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}