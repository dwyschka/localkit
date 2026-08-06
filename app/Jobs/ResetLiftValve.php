<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\ResetLiftValveMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class ResetLiftValve implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Device $device)
    {
        //
    }

    public function handle(): void
    {
        $message = ResetLiftValveMessage::send($this->device);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
