<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\PropertySetMessage;
use App\MQTT\ServiceStartMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class FeedRealtime implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Device $device, protected int $startAction)
    {
        //
    }

    //{"method":"thing.service.feed_realtime","id":"18432013529","params":{"amount":10,"id":"r_20250629_81113389_81099-1"},"version":"1.0.0"}
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = ServiceStartMessage::send($this->device, $this->startAction);
        MQTT::connection('publisher')->publish($message->getTopic(), $message->getMessage());
    }
}
