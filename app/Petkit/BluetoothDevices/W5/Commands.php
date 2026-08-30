<?php

namespace App\Petkit\BluetoothDevices\W5;

/**
 * Builds raw BLE command frames for the W5 fountain family. Byte-for-byte
 * port of slespersen/PetkitW5BLEMQTT's Utils.build_command() - the same
 * FA FC FD ... FB framing our own Parser::parseFrame() decodes on the way
 * in. Confirmed against a live capture of cmd 215 (set_light_setting)
 * relayed over `thing/service/ble` (see App\Http\Resources\MQTT\ServiceBle):
 * decoded frame was fa fc fd d7 01 05 00 00 fb - cmd=0xD7=215, type=1
 * (send), seq=5, length=0, no data.
 */
class Commands
{
    private const HEADER = [0xFA, 0xFC, 0xFD];
    private const END_BYTE = 0xFB;

    // "Send" direction - the only type we ever encode ourselves, receiving
    // a reply is type 2 and only ever appears in what the device sends back.
    private const TYPE_SEND = 1;

    public const CMD_SET_LIGHT = 215;
    public const CMD_SET_DND = 216;
    public const CMD_SET_MODE = 220;
    public const CMD_SET_CONFIG = 221;
    public const CMD_RESET_FILTER = 222;

    /**
     * @param int[] $data
     */
    public static function build(int $seq, int $cmd, array $data): string
    {
        $bytes = [
            ...self::HEADER,
            $cmd,
            self::TYPE_SEND,
            $seq,
            count($data),
            0,
            ...$data,
            self::END_BYTE,
        ];

        return implode('', array_map('chr', $bytes));
    }

    /**
     * @param int $state 1 = on, 0 = off
     * @param int $mode 1 = normal, 2 = smart
     */
    public static function setMode(int $seq, int $state, int $mode): string
    {
        return self::build($seq, self::CMD_SET_MODE, [$state, $mode]);
    }

    public static function resetFilter(int $seq): string
    {
        return self::build($seq, self::CMD_RESET_FILTER, [0]);
    }
}
