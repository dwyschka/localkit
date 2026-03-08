<?php
namespace App\Petkit\BluetoothDevices\W5;

/**
 * Petkit BLE Message Parser
 *
 * Decodes BLE messages from Petkit smart water fountains.
 * Mirrors the Python parsers.py / utils.py logic.
 *
 * Usage:
 *   $parser = new PetkitBleParser('W5');
 *   $result = $parser->decode('FAFCFD...');
 *   $result = $parser->decodeRaw('0101000000...', 230);
 */
class Parser
{
    public const CMD_BATTERY = 66;
    public const CMD_SYNCHRONIZATION = 86;
    public const CMD_FIRMWARE = 200;
    public const CMD_DEVICE_STATE = 210;
    public const CMD_DEVICE_CONFIGURATION = 211;
    public const CMD_DEVICE_STATUS = 230;

    private const FRAME_HEADER = [0xFA, 0xFC, 0xFD];
    private const FRAME_END = 0xFB;

    private const FLOW_RATES = [
        'W5C' => 1.3,
    ];
    private const DEFAULT_FLOW_RATE = 1.5;

    private const PUMP_DIVISORS = [
        'W5C'  => 1.0,
        'W4X'  => 1.8,
        'CTW3' => 3.0,
    ];
    private const DEFAULT_PUMP_DIVISOR = 2.0;

    private const POWER_WATTS = [
        'W5C' => 0.182,
    ];
    private const DEFAULT_POWER_WATTS = 0.75;

    protected string $alias;

    public function __construct(string $alias = 'W5')
    {
        $this->alias = $alias;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): static
    {
        $this->alias = $alias;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    public function decode(string $hex, ?int $forceCmd = null): array
    {
        $bytes = self::hexToBytes($hex);
        $frame = $this->parseFrame($bytes);

        if ($frame !== null) {
            $cmd  = $frame['cmd'];
            $data = $frame['data'];
        } elseif ($forceCmd !== null) {
            $cmd  = $forceCmd;
            $data = $bytes;
        } else {
            throw new \InvalidArgumentException(
                'No FAFCFD header found. Provide $forceCmd to decode a raw payload.'
            );
        }

        return [
            'cmd'     => $cmd,
            'alias'   => $this->alias,
            'frame'   => $frame,
            'decoded' => $this->dispatch($cmd, $data),
        ];
    }

    public function decodeRaw(string $hex, int $cmd): array
    {
        return $this->decode($hex, $cmd);
    }

    // -----------------------------------------------------------------------
    // Frame parsing
    // -----------------------------------------------------------------------

    protected function parseFrame(array $bytes): ?array
    {
        if (count($bytes) < 9) {
            return null;
        }

        if (
            $bytes[0] !== self::FRAME_HEADER[0] ||
            $bytes[1] !== self::FRAME_HEADER[1] ||
            $bytes[2] !== self::FRAME_HEADER[2]
        ) {
            return null;
        }

        return [
            'header'     => array_slice($bytes, 0, 3),
            'cmd'        => $bytes[3],
            'type'       => $bytes[4],
            'seq'        => $bytes[5],
            'dataLength' => $bytes[6],
            'dataStart'  => $bytes[7],
            'data'       => array_slice($bytes, 8, count($bytes) - 9),
            'endByte'    => $bytes[count($bytes) - 1],
        ];
    }

    // -----------------------------------------------------------------------
    // Command dispatch
    // -----------------------------------------------------------------------

    protected function dispatch(int $cmd, array $data): array
    {
        return match ($cmd) {
            self::CMD_BATTERY              => $this->parseBattery($data),
            self::CMD_SYNCHRONIZATION      => $this->parseSynchronization($data),
            self::CMD_FIRMWARE             => $this->parseFirmware($data),
            self::CMD_DEVICE_STATE         => $this->parseDeviceState($data),
            self::CMD_DEVICE_CONFIGURATION => $this->parseDeviceConfiguration($data),
            self::CMD_DEVICE_STATUS        => $this->parseDeviceStatus($data),
            default                        => $this->parseUnknown($cmd, $data),
        };
    }

    // -----------------------------------------------------------------------
    // Individual parsers
    // -----------------------------------------------------------------------

    protected function parseBattery(array $data): array
    {
        $voltage = (($data[0] * 256) + ($data[1] & 0xFF)) / 1000.0;

        return [
            'voltage' => $voltage,
            'battery' => $data[2],
        ];
    }

    protected function parseSynchronization(array $data): array
    {
        return ['deviceInitialized' => $data[0]];
    }

    protected function parseFirmware(array $data): array
    {
        return ['firmware' => (float) "{$data[0]}.{$data[1]}"];
    }

    protected function parseDeviceState(array $data): array
    {
        if ($this->alias === 'CTW3') {
            return $this->parseDeviceStateCTW3($data);
        }

        return [
            'powerStatus'         => $data[0],
            'mode'                => $data[1],
            'dndState'            => $data[2],
            'warningBreakdown'    => $data[3],
            'warningWaterMissing' => $data[4],
            'warningFilter'       => $data[5],
            'pumpRuntime'         => self::bytesToInteger(array_slice($data, 6, 4)),
            'filterPercentage'    => ($data[10] & 0xFF) / 100,
            'runningStatus'       => $data[11] & 0xFF,
        ];
    }

    protected function parseDeviceStateCTW3(array $data): array
    {
        if (count($data) < 26) {
            throw new \UnderflowException('Insufficient data for CTW3 device state (need 26 bytes, got ' . count($data) . ')');
        }

        return [
            'powerStatus'         => $data[0],
            'suspendStatus'       => $data[1],
            'mode'                => $data[2],
            'electricStatus'      => $data[3],
            'dndState'            => $data[4],
            'warningBreakdown'    => $data[5],
            'warningWaterMissing' => $data[6],
            'lowBattery'          => $data[7],
            'warningFilter'       => $data[8],
            'pumpRuntime'         => self::bytesToInteger(array_slice($data, 9, 4)),
            'filterPercentage'    => $data[13],
            'runningStatus'       => $data[14],
            'pumpRuntimeToday'    => self::bytesToInteger(array_slice($data, 15, 4)),
            'detectStatus'        => $data[19],
            'supplyVoltage'       => self::bytesToShort(array_slice($data, 20, 2)),
            'batteryVoltage'      => self::bytesToShort(array_slice($data, 22, 2)),
            'batteryPercentage'   => $data[24],
            'moduleStatus'        => $data[25],
        ];
    }

    protected function parseDeviceConfiguration(array $data): array
    {
        if ($this->alias === 'CTW3') {
            return $this->parseDeviceConfigurationCTW3($data);
        }

        $ledOn  = self::bytesToShort(array_slice($data, 4, 2));
        $ledOff = self::bytesToShort(array_slice($data, 6, 2));
        $dndOn  = self::bytesToShort(array_slice($data, 9, 2));
        $dndOff = self::bytesToShort(array_slice($data, 11, 2));

        return [
            'smartTimeOn'                 => $data[0],
            'smartTimeOff'                => $data[1],
            'ledSwitch'                   => $data[2],
            'ledBrightness'               => $data[3],
            'ledLightTimeOn'              => $ledOn,
            'ledLightTimeOnReadable'      => self::minutesToTimestamp($ledOn),
            'ledLightTimeOff'             => $ledOff,
            'ledLightTimeOffReadable'     => self::minutesToTimestamp($ledOff),
            'doNotDisturbSwitch'          => $data[8],
            'doNotDisturbTimeOn'          => $dndOn,
            'doNotDisturbTimeOnReadable'  => self::minutesToTimestamp($dndOn),
            'doNotDisturbTimeOff'         => $dndOff,
            'doNotDisturbTimeOffReadable' => self::minutesToTimestamp($dndOff),
            'isLocked'                    => $data[13] ?? null,
        ];
    }

    protected function parseDeviceConfigurationCTW3(array $data): array
    {
        $batteryWorkingTime = self::bytesToShort(array_slice($data, 2, 2));
        $batterySleepTime   = self::bytesToShort(array_slice($data, 4, 2));

        return [
            'smartTimeOn'        => $data[0],
            'smartTimeOff'       => $data[1],
            'batteryWorkingTime' => $batteryWorkingTime,
            'batteryTimeOn'      => self::minutesToTimestamp($batteryWorkingTime),
            'batterySleepTime'   => $batterySleepTime,
            'batteryTimeOff'     => self::minutesToTimestamp($batterySleepTime),
            'ledSwitch'          => $data[6],
            'ledBrightness'      => $data[7],
            'doNotDisturbSwitch' => $data[8],
            'isLocked'           => $data[9] ?? 0,
        ];
    }

    protected function parseDeviceStatus(array $data): array
    {
        $mode            = $data[1];
        $filterPct       = ($data[10] & 0xFF);
        $smartTimeOn     = $data[16];
        $smartTimeOff    = $data[17];
        $pumpRuntime     = self::bytesToInteger(array_slice($data, 6, 4));
        $pumpRuntimeToday= self::bytesToInteger(array_slice($data, 12, 4));
        $ledOn           = self::bytesToShort(array_slice($data, 20, 2));
        $ledOff          = self::bytesToShort(array_slice($data, 22, 2));
        $dndOn           = self::bytesToShort(array_slice($data, 25, 2));
        $dndOff          = self::bytesToShort(array_slice($data, 27, 2));

        $tOn  = ($mode === 1) ? 1 : $smartTimeOn;
        $tOff = ($mode === 1) ? 0 : $smartTimeOff;

        $filterTimeLeft     = $this->calculateRemainingFilterDays($filterPct, $tOn, $tOff);
        $purifiedWater      = $this->calculateWaterPurified($pumpRuntime);
        $purifiedWaterToday = $this->calculateWaterPurified($pumpRuntimeToday);
        $energyConsumed     = $this->calculateEnergyUsage($pumpRuntime);

        return [
            'powerStatus'                    => $data[0],
            'mode'                           => $mode,
            'dndState'                       => $data[2],
            'warningBreakdown'               => $data[3],
            'warningWaterMissing'            => $data[4],
            'warningFilter'                  => $data[5],
            'pumpRuntime'                    => $pumpRuntime,
            'pumpRuntimeReadable'            => self::secondsToDaysHours($pumpRuntime),
            'filterPercentage'               => $filterPct,
            'runningStatus'                  => $data[11] & 0xFF,
            'pumpRuntimeToday'               => $pumpRuntimeToday,
            'pumpRuntimeTodayReadable'       => self::secondsToHoursMinutes($pumpRuntimeToday),
            'smartTimeOn'                    => $smartTimeOn,
            'smartTimeOff'                   => $smartTimeOff,
            'ledSwitch'                      => $data[18],
            'ledBrightness'                  => $data[19],
            'ledLightTimeOn'                 => $ledOn,
            'ledLightTimeOnReadable'         => self::minutesToTimestamp($ledOn),
            'ledLightTimeOff'                => $ledOff,
            'ledLightTimeOffReadable'        => self::minutesToTimestamp($ledOff),
            'doNotDisturbSwitch'             => $data[24],
            'doNotDisturbTimeOn'             => $dndOn,
            'doNotDisturbTimeOnReadable'     => self::minutesToTimestamp($dndOn),
            'doNotDisturbTimeOff'            => $dndOff,
            'doNotDisturbTimeOffReadable'    => self::minutesToTimestamp($dndOff),
            'filterTimeLeftDays'             => $filterTimeLeft,
            'purifiedWaterLiters'            => round($purifiedWater, 2),
            'purifiedWaterTodayLiters'       => round($purifiedWaterToday, 2),
            'energyConsumedKwh'              => number_format($energyConsumed, 6),
        ];
    }

    protected function parseUnknown(int $cmd, array $data): array
    {
        return [
            'cmdUnknown' => $cmd,
            'raw'        => array_map(fn($b) => sprintf('0x%02X', $b), $data),
        ];
    }

    // -----------------------------------------------------------------------
    // Derived value calculations
    // -----------------------------------------------------------------------

    protected function calculateRemainingFilterDays(float $filterPct, int $timeOn, int $timeOff): int
    {
        if ($timeOn === 0) {
            return 0;
        }

        return (int) ceil((($filterPct * 30.0) * ($timeOn + $timeOff)) / $timeOn);
    }

    protected function calculateWaterPurified(int $pumpRuntimeSeconds): float
    {
        $flowRate = self::FLOW_RATES[$this->alias] ?? self::DEFAULT_FLOW_RATE;
        $divisor  = self::PUMP_DIVISORS[$this->alias] ?? self::DEFAULT_PUMP_DIVISOR;

        return ($flowRate * $pumpRuntimeSeconds) / 60.0 / $divisor;
    }

    protected function calculateEnergyUsage(int $pumpRuntimeSeconds): float
    {
        $powerW = self::POWER_WATTS[$this->alias] ?? self::DEFAULT_POWER_WATTS;

        return ($powerW * $pumpRuntimeSeconds) / 3600.0 / 1000.0;
    }

    // -----------------------------------------------------------------------
    // Static utility methods
    // -----------------------------------------------------------------------

    public static function hexToBytes(string $hex): array
    {
        $hex = strtolower(preg_replace('/\s+/', '', $hex));

        if (!ctype_xdigit($hex) || strlen($hex) % 2 !== 0) {
            throw new \InvalidArgumentException('Invalid hex string');
        }

        return array_values(array_map('hexdec', str_split($hex, 2)));
    }

    public static function bytesToInteger(array $bytes): int
    {
        $result = 0;
        foreach ($bytes as $b) {
            $result = ($result << 8) | ($b & 0xFF);
        }
        return $result;
    }

    public static function bytesToShort(array $bytes): int
    {
        $val = self::bytesToInteger($bytes);
        if ($val >= 0x8000) {
            $val -= 0x10000;
        }
        return $val;
    }

    public static function minutesToTimestamp(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function secondsToDaysHours(int $seconds): string
    {
        $days  = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        return "{$days} days, {$hours} hours";
    }

    public static function secondsToHoursMinutes(int $seconds): string
    {
        return sprintf('%d:%02dh', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
