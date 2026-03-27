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

        if($cmd != 230) {
            Log::info('W5', ['cmd' => $cmd, 'payload' => $payload]);

            return;
        }
        Log::info('W5', ['cmd' => $cmd, 'payload' => $payload]);
        $binary = bin2hex(base64_decode(urldecode($payload)));
        $decode = $this->parser()->decode($binary, $cmd);
        Log::info('Decoded', ['decode' => $decode]);

        $configuration = Configuration::fromParser($decode['decoded']);

        Log::info('From Parser', ['msg' => $configuration]);

        $this->model->configuration = $configuration->toArray();

        $this->model->save();




        $msg = json_decode('{
      "powerStatus": 1,
      "mode": 1,
      "dndState": 0,
      "warningBreakdown": 0,
      "warningWaterMissing": 0,
      "warningFilter": 0,
      "pumpRuntime": 2185486,
      "pumpRuntimeReadable": "25 days, 7 hours",
      "filterPercentage": 15,
      "runningStatus": 1,
      "pumpRuntimeToday": 82500,
      "pumpRuntimeTodayReadable": "22:55h",
      "smartTimeOn": 3,
      "smartTimeOff": 3,
      "ledSwitch": 0,
      "ledBrightness": 2,
      "ledLightTimeOn": 0,
      "ledLightTimeOnReadable": "00:00",
      "ledLightTimeOff": 1440,
      "ledLightTimeOffReadable": "24:00",
      "doNotDisturbSwitch": 0,
      "doNotDisturbTimeOn": 1320,
      "doNotDisturbTimeOnReadable": "22:00",
      "doNotDisturbTimeOff": 256,
      "doNotDisturbTimeOffReadable": "04:16",
      "filterTimeLeftDays": 450,
      "purifiedWaterLiters": 27318.58,
      "purifiedWaterTodayLiters": 1031.25,
      "energyConsumedKwh": "0.455310"
    }');
    }

    public function deviceName(): string {
        return 'Water Fountain (W5)';
    }
}
