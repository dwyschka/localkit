<?php

namespace App\Jobs;

use App\Models\BluetoothDevice;
use App\Models\Device;
use App\MQTT\PropertySetMessage;
use App\MQTT\ServiceConnectMessage;
use App\MQTT\ServiceStartMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PhpMqtt\Client\Facades\MQTT;

class ServiceConnect implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Device $device, protected BluetoothDevice $bluetoothDevice)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = ServiceConnectMessage::send($this->device, $this->bluetoothDevice);

        $connection = MQTT::connection('publisher');
        $connection->publish($message->getTopic(), $message->getMessage());
        $connection->disconnect();
    }
}
