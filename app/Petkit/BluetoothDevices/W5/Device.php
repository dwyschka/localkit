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
use App\Homeassistant\HomeassistantTopic;
use Illuminate\Support\Facades\Log;
use UnderflowException;

class Device implements DeviceInterface, HasParserInterface
{
    use BluetoothDeviceTrait;
    public function __construct(protected BluetoothDevice $model) {}

    protected Parser $parser;

    protected array $actions = [
        Actions::REFRESH,
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

        try {
            $decode = $this->parser()->decode($binary, $cmd);
        } catch (UnderflowException $e) {
            // A short/partial frame (e.g. a mid-handshake ack) would
            // otherwise silently parse into zeroed-out fields and
            // overwrite the last known-good state - reject it instead.
            Log::warning('W5 status frame too short to parse, ignoring', [
                'bluetooth_device_id' => $this->model->id,
                'binary' => $binary,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        Log::info('Decoded', ['decode' => $decode]);

        $configuration = Configuration::fromParser($decode['decoded']);

        // fromParser() only ever sees the decoded status frame, which
        // carries none of our own bookkeeping - carry the BLE sequence
        // counter across so it doesn't reset to 0 on every status update.
        $configuration->bleSequence = $this->model->configuration()->bleSequence;
        $configuration->lastUpdate = now()->toIso8601String();

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
     * Home Assistant equivalent of editing Power/Mode on the record's
     * detail page - the Power switch and Mode select both command here
     * (see W5\Configuration's powerStatus/modeReadable attributes), each
     * only ever setting its own key. Fall back to the currently known
     * value for whichever key is absent, since the device only accepts
     * state+mode together in one command.
     */
    #[HomeassistantTopic('setting/set')]
    public function settings(stdClass $message): void
    {
        if (!isset($message->powerStatus) && !isset($message->mode)) {
            return;
        }

        $configuration = $this->model->configuration();

        $state = isset($message->powerStatus) ? (int) $message->powerStatus : (int) $configuration->powerStatus;
        $mode = isset($message->mode) ? (int) $message->mode : ($configuration->mode ?: 1);

        $this->setMode($state, $mode);
    }

    #[HomeassistantTopic('action/start')]
    public function action(stdClass $message): void
    {
        if (($message->action ?? null) === 'reset_filter') {
            $this->resetFilter();
        }
    }

    /**
     * Power/mode are edited directly on the record's detail page (rather
     * than via separate "Turn On"/"Turn Off" buttons) - saving the form
     * lands here, where a change to either gets turned into a single
     * setMode() write (the device takes both together in one command).
     */
    public function propertyChange(BluetoothDevice $device): void
    {
        $old = $device->getOriginal('configuration')['states'] ?? [];
        $new = $device->configuration['states'] ?? [];

        $oldPower = (int) ($old['powerStatus'] ?? 0);
        $newPower = (int) ($new['powerStatus'] ?? 0);
        $oldMode  = (int) ($old['mode'] ?? 1);
        $newMode  = (int) ($new['mode'] ?? 1);

        if ($oldPower === $newPower && $oldMode === $newMode) {
            return;
        }

        $this->setMode($newPower, $newMode ?: 1);
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

        // saveQuietly() - a plain save() here would refire the model's
        // `updated` event (BluetoothDevice::booted()), which re-publishes
        // to Home Assistant and calls propertyChange() again. Called from
        // inside sendCommand(), which itself already runs from that same
        // event (a form save changing power/mode) - each nested save was
        // stacking another MQTT connect/publish/disconnect on top, which is
        // what made saving feel like it hung.
        $this->model->saveQuietly();

        return $seq;
    }

    public function deviceName(): string {
        return 'Water Fountain (W5)';
    }
}
