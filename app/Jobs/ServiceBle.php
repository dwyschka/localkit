<?php

namespace App\Jobs;

use App\Models\BluetoothDevice;
use App\Models\Device;
use App\MQTT\ServiceBleMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class ServiceBle implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Device $device,
        protected BluetoothDevice $bluetoothDevice,
        protected string $rawCommand,
        protected int $cmd,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = ServiceBleMessage::send($this->device, $this->bluetoothDevice, $this->rawCommand, $this->cmd);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
