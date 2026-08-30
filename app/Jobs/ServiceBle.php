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
     *
     * @param string $commandBase64 Base64-encoded raw BLE command frame -
     *        never pass raw binary, it isn't valid UTF-8 and breaks the
     *        queue's payload serialization (json_encode on the whole
     *        payload happens even for dispatchSync()).
     */
    public function __construct(
        protected Device $device,
        protected BluetoothDevice $bluetoothDevice,
        protected string $commandBase64,
        protected int $cmd,
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = ServiceBleMessage::send($this->device, $this->bluetoothDevice, $this->commandBase64, $this->cmd);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
