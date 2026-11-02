<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\ServicePowerMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class ServicePower implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Device $device, protected int $powerAction)
    {
    }

    public function handle(): void
    {
        $message = ServicePowerMessage::send($this->device, $this->powerAction);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
