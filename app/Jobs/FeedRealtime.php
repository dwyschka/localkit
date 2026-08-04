<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\FeedRealtimeMessage;
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
    public function __construct(protected Device $device, protected int $amount, protected ?int $amount2 = null)
    {
        //
    }

    //{"method":"thing.service.feed_realtime","id":"18432013529","params":{"amount":10,"id":"r_20250629_81113389_81099-1"},"version":"1.0.0"}
    // Dual hopper: {"method":"thing.service.feed_realtime","id":"946197815","params":{"id":"r_20260726_74117_74117-1","amount2":1,"amount1":3},"version":"1.0.0"}
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payload = $this->amount2 === null
            ? ['amount' => $this->amount]
            : ['amount1' => $this->amount, 'amount2' => $this->amount2];

        $message = FeedRealtimeMessage::send($this->device, $payload);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
