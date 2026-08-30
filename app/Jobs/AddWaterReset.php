<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\AddWaterResetMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class AddWaterReset implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Device $device)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = AddWaterResetMessage::send($this->device);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
