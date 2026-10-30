<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\ServiceContinueMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class ServiceContinue implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Device $device, protected int $continueAction)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = ServiceContinueMessage::send($this->device, $this->continueAction);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
