<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\PropertySetMessage;
use App\MQTT\ServiceEndMessage;
use App\MQTT\ServiceStartMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class ServiceEnd implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Device $device, protected int $endAction)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = ServiceEndMessage::send($this->device, $this->endAction);
        MQTT::connection('publisher')->publish($message->getTopic(), $message->getMessage());
    }
}
