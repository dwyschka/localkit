<?php

namespace App\Petkit\BluetoothDevices\W5;

use App\Models\BluetoothDevice;
use App\Petkit\BluetoothDevices\Actions;
use App\Petkit\BluetoothDevices\BluetoothDeviceTrait;
use App\Petkit\BluetoothDevices\DeviceInterface;
use App\Petkit\BluetoothDevices\HasParserInterface;
use App\Petkit\BluetoothDevices\W5\Parser;
use Illuminate\Support\Facades\Log;

class Device implements DeviceInterface, HasParserInterface
{
    use BluetoothDeviceTrait;
    public function __construct(protected BluetoothDevice $model) {}

    protected Parser $parser;

    protected array $actions = [
        Actions::REFRESH
    ];

    public function hasAction(string $action): bool
    {
        return in_array($action, $this->actions);
    }

    protected function parser(): Parser
    {
        return new Parser();
    }


    public function handleMessage(\stdClass $message): bool
    {
        $cmd = $message->cmd;
        $payload = $message->data;

        Log::info('W5', ['cmd' => $cmd, 'payload' => $payload]);
        $binary = bin2hex(base64_decode(urldecode($payload)));
        $decode = $this->parser()->decode($binary, $cmd);
        Log::info('Decoded', ['decode' => $decode]);

        $configuration = Configuration::fromParser($decode['decoded']);

        Log::info('From Parser', ['msg' => $configuration]);

        $this->model->configuration = $configuration->toArray();

        $this->model->save();
    }

    public function deviceName(): string {
        return 'Water Fountain (W5)';
    }
}
