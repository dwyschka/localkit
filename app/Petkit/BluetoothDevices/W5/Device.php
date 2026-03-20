<?php

namespace App\Petkit\BluetoothDevices\W5;

use App\Models\BluetoothDevice;
use App\Petkit\BluetoothDevices\Actions;
use App\Petkit\BluetoothDevices\DeviceInterface;
use App\Petkit\BluetoothDevices\HasParserInterface;
use App\Petkit\BluetoothDevices\W5\Parser;

class Device implements DeviceInterface, HasParserInterface
{
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

        $binary = bin2hex(base64_decode(urldecode($payload)));
        $decodedMessage = $this->parser()->decode($binary, $cmd);
        $configuration = Configuration::fromParser($decodedMessage['decoded']);

        $this->model->configuration = $configuration->toArray();

        $this->model->save();

    }
}
