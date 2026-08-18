<?php

namespace App\Petkit\BluetoothDevices\W5;

use stdClass;
use App\Models\BluetoothDevice;
use App\Petkit\BluetoothDevices\Actions;
use App\Petkit\BluetoothDevices\BluetoothDeviceTrait;
use App\Petkit\BluetoothDevices\BluetoothProxyInterface;
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
        Actions::REFRESH,
        Actions::MODE_NORMAL,
        Actions::MODE_SMART,
        Actions::POWER_OFF,
        Actions::RESET_FILTER,
    ];

    public function hasAction(string $action): bool
    {
        return in_array($action, $this->actions);
    }

    protected function parser(): Parser
    {
        return new Parser();
    }


    public function handleMessage(stdClass $message): bool
    {
        $cmd = $message->cmd;
        $payload = $message->data;

        Log::info('W5', ['cmd' => $cmd, 'payload' => $payload]);

        if($cmd != 230) {
            return false;
        }
        Log::info('W5', ['cmd' => $cmd, 'payload' => $payload]);
        $binary = bin2hex(base64_decode(urldecode($payload)));
        $decode = $this->parser()->decode($binary, $cmd);
        Log::info('Decoded', ['decode' => $decode]);

        $configuration = Configuration::fromParser($decode['decoded']);

        // fromParser() only ever sees the decoded status frame, which
        // carries none of our own bookkeeping - carry the BLE sequence
        // counter across so it doesn't reset to 0 on every status update.
        $configuration->bleSequence = $this->model->configuration()->bleSequence;

        Log::info('From Parser', ['msg' => $configuration]);

        $this->model->configuration = $configuration->toArray();

        $this->model->save();

        return true;

    }

    /**
     * @param int $state 1 = on, 0 = off
     * @param int $mode 1 = normal, 2 = smart
     */
    public function setMode(int $state, int $mode): void
    {
        $this->sendCommand(fn (int $seq) => Commands::setMode($seq, $state, $mode), Commands::CMD_SET_MODE);
    }

    public function resetFilter(): void
    {
        $this->sendCommand(fn (int $seq) => Commands::resetFilter($seq), Commands::CMD_RESET_FILTER);
    }

    /**
     * @param callable(int): string $buildFrame Builds the raw command frame once a sequence number is reserved.
     */
    private function sendCommand(callable $buildFrame, int $cmd): void
    {
        $proxyDevice = $this->model->linkWith;

        if ($proxyDevice === null) {
            Log::warning('W5 command has no linked proxy device to relay through', [
                'bluetooth_device_id' => $this->model->id,
                'cmd' => $cmd,
            ]);
            return;
        }

        $definition = $proxyDevice->definition();

        if (!($definition instanceof BluetoothProxyInterface)) {
            Log::warning('W5 command\'s linked proxy device cannot relay BLE writes', [
                'bluetooth_device_id' => $this->model->id,
                'cmd' => $cmd,
            ]);
            return;
        }

        $seq = $this->nextSequence();

        // Encode to base64 here, before the raw frame bytes (not valid
        // UTF-8) ever touch a queued job - Laravel's payload builder
        // json_encode()s the whole thing even for dispatchSync(), which
        // chokes on raw binary.
        $definition->btWrite($this->model, base64_encode($buildFrame($seq)), $cmd);
    }

    private function nextSequence(): int
    {
        $configuration = $this->model->configuration();
        $seq = ($configuration->bleSequence + 1) % 256;

        $configuration->bleSequence = $seq;
        $this->model->configuration = $configuration->toArray();
        $this->model->save();

        return $seq;
    }

    public function deviceName(): string {
        return 'Water Fountain (W5)';
    }
}
