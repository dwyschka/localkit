<?php

namespace Tests\Unit;

use App\Petkit\BluetoothDevices\W5\Parser;
use PHPUnit\Framework\TestCase;

class W5ParserTest extends TestCase
{
    /**
     * CMD 230 status payload relayed from an Eversweet Max Cordless (CTW3).
     */
    private const CTW3_STATUS = 'AQEBAgAAAAAAABJsMjUBAAEabAATaBBgZABhB1wHAwMAGQ4QAQIAAAAA';

    public function test_ctw3_status_payload_is_detected_by_length(): void
    {
        $this->assertSame('CTW3', Parser::aliasForStatusPayload(bin2hex(base64_decode(self::CTW3_STATUS))));
        $this->assertSame('W5', Parser::aliasForStatusPayload(str_repeat('00', 29)));
        $this->assertSame('W5', Parser::aliasForStatusPayload('fafcfd' . str_repeat('00', 34) . 'fb'));
    }

    public function test_ctw3_status_payload_decodes(): void
    {
        $decoded = (new Parser('CTW3'))->decode(bin2hex(base64_decode(self::CTW3_STATUS)), 230)['decoded'];

        $this->assertSame(1, $decoded['powerStatus']);
        $this->assertSame(1, $decoded['mode']);
        $this->assertSame(1, $decoded['runningStatus']);
        $this->assertSame(53, $decoded['filterPercentage']);
        $this->assertSame(1207346, $decoded['pumpRuntime']);
        $this->assertSame(72300, $decoded['pumpRuntimeToday']);
        $this->assertSame(4968, $decoded['supplyVoltage']);
        $this->assertSame(4192, $decoded['batteryVoltage']);
        $this->assertSame(100, $decoded['batteryPercentage']);
        $this->assertSame(3, $decoded['smartTimeOn']);
        $this->assertSame(3, $decoded['smartTimeOff']);
        $this->assertSame(1, $decoded['ledSwitch']);
        $this->assertSame(2, $decoded['ledBrightness']);
        $this->assertSame(0, $decoded['doNotDisturbSwitch']);
    }

    public function test_w5_status_payload_still_uses_w5_layout(): void
    {
        $data = array_fill(0, 29, 0);
        $data[0] = 1;   // power
        $data[1] = 2;   // mode
        $data[10] = 80; // filter %
        $data[11] = 1;  // running

        $decoded = (new Parser('W5'))->decode(implode('', array_map(fn($b) => sprintf('%02x', $b), $data)), 230)['decoded'];

        $this->assertSame(1, $decoded['powerStatus']);
        $this->assertSame(2, $decoded['mode']);
        $this->assertSame(80, $decoded['filterPercentage']);
        $this->assertSame(1, $decoded['runningStatus']);
    }

    /**
     * CMD 210 and the CMD 230 a few seconds earlier, both from an Eversweet
     * Solo 2 in Smart mode relayed through a D4: 210 is the first 16 bytes
     * of 230's layout.
     */
    private const W5_STATE = 'AQIAAAAAAADgaGEBAAB1jw==';
    private const W5_STATUS = 'AQIAAAAAAADgRWEBAAB1bAMDAQIAAAWgAAUoAWgA';

    public function test_w5_state_payload_matches_status_layout(): void
    {
        $hex = bin2hex(base64_decode(self::W5_STATE));
        $this->assertSame('W5', Parser::aliasForStatePayload($hex));
        $this->assertSame('CTW3', Parser::aliasForStatePayload(str_repeat('00', 26)));

        $state = (new Parser('W5'))->decode($hex, 210)['decoded'];
        $status = (new Parser('W5'))->decode(bin2hex(base64_decode(self::W5_STATUS)), 230)['decoded'];

        foreach (['powerStatus', 'mode', 'dndState', 'warningBreakdown', 'warningWaterMissing', 'warningFilter', 'filterPercentage', 'runningStatus'] as $key) {
            $this->assertSame($status[$key], $state[$key], $key);
        }
        $this->assertSame(97, $state['filterPercentage']);
        $this->assertSame(57448, $state['pumpRuntime']);
        $this->assertSame(30095, $state['pumpRuntimeToday']);
    }

    public function test_state_merged_over_status_recomputes_derived_fields(): void
    {
        $parser = new Parser('W5');
        $status = $parser->decode(bin2hex(base64_decode(self::W5_STATUS)), 230)['decoded'];
        $state = $parser->decode(bin2hex(base64_decode(self::W5_STATE)), 210)['decoded'];

        $merged = $parser->withDerivedFields(array_merge($status, $state));

        $this->assertSame(3, $merged['smartTimeOn']);
        $this->assertSame(1, $merged['ledSwitch']);
        $this->assertSame(30095, $merged['pumpRuntimeToday']);
        $this->assertSame('8:21h', $merged['pumpRuntimeTodayReadable']);
        // 97% of 30 days, doubled by Smart's 3 min on / 3 off.
        $this->assertSame(59, $merged['filterTimeLeftDays']);
    }
}
