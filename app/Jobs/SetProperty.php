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

        // Connect first, *then* (re)build the 'feed' payload - testing the
        // hypothesis that the 't'/'nextTick' countdown (seconds-until-next-
        // feed, computed off Carbon::now() inside Time::calculateLatest())
        // goes stale between calculation and the device actually receiving
        // it. A fresh MQTT connection per publish (no persistent connection
        // pool here) is the biggest known variable-latency step in this
        // path - TCP/TLS handshake + CONNECT/CONNACK can run from tens to
        // hundreds of ms. Previously toFeed() ran once in propertyChange(),
        // before dispatchSync() even queued this job, so that connection
        // time landed entirely *after* 't' was already frozen. Recomputing
        // here, after connect(), shrinks the calculation-to-wire gap down to
        // just the publish() call itself.
        $connection = MQTT::connection('publisher');

        if (array_key_exists('feed', $this->changes) && method_exists($this->device->definition(), 'toFeed')) {
            $this->changes['feed'] = $this->device->definition()->toFeed($this->device);
        }

        // PropertySetMessage::send() already accepts a multi-key array and
        // builds one message from it - splitting into one publish per key
        // just meant the device saw N separate property_set messages for a
        // save that changed N properties at once, instead of the one
        // combined message the real app/device firmware expects (and that
        // BLE devices already send via a single setMode() write).
        $message = PropertySetMessage::send($this->device, $this->changes);

        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
