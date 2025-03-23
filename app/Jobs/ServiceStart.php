<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\PropertySetMessage;
use App\MQTT\ServiceStartMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class ServiceStart implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Device $device, protected int $startAction)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = ServiceStartMessage::send($this->device, $this->startAction);
        MQTT::publish($message->getTopic(), $message->getMessage());
    }
}
