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
        if (empty($this->changes)) {
            return;
        }

        // PropertySetMessage::send() already accepts a multi-key array and
        // builds one message from it - splitting into one publish per key
        // just meant the device saw N separate property_set messages for a
        // save that changed N properties at once, instead of the one
        // combined message the real app/device firmware expects (and that
        // BLE devices already send via a single setMode() write).
        $message = PropertySetMessage::send($this->device, $this->changes);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
