<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\ServiceStopMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class ServiceStop implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Device $device, protected int $stopAction)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = ServiceStopMessage::send($this->device, $this->stopAction);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
