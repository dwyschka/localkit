<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\PropertySetMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

class SetProperty implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Device $device, protected array $changes)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        foreach($this->changes as $key => $change) {
            $message = PropertySetMessage::send($this->device, [$key => $change]);
            MQTT::publish($message->getTopic(), $message->getMessage());
        }

    }
}
