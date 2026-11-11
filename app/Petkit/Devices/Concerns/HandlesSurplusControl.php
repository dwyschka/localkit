<?php

namespace App\Petkit\Devices\Concerns;

/**
 * Surplus Food Control for the camera feeders (YumshareDual/D4SH,
 * YumshareSolo/D4H). The settings keep the Petkit app's shape -
 * surplusControl 0/1 plus surplusStandard 1 (Low) / 2 (Moderate) / 3 (Full) -
 * but the firmware reads surplusControl as the threshold itself: the D4SH's
 * ble process (pk_food_surplus_ctrl_check) only lets a feed through while
 * surplusControl > the camera's leftover-food estimate in percent, and only
 * checks surplusStandard for != 0. Sending the app's 1 therefore skipped every
 * feed unless the bowl looked completely empty.
 *
 * The Petkit cloud does the translation, captured 2026-11-01 from its
 * thing.service.property.set to a D4SH:
 *   Less     {"surplusControl":30,"surplusStandard":1}
 *   Moderate {"surplusControl":60,"surplusStandard":2}
 *   Full     {"surplusControl":80,"surplusStandard":3}
 */
trait HandlesSurplusControl
{
    public const SURPLUS_THRESHOLDS = [
        1 => 30, // Low
        2 => 60, // Moderate
        3 => 80, // Full
    ];

    /** surplusControl as the device has to receive it: 0, or the chosen level's percentage. */
    public static function surplusThreshold(int $control, int $standard): int
    {
        if ($control === 0) {
            return 0;
        }

        return self::SURPLUS_THRESHOLDS[self::surplusStandardForWire($standard)];
    }

    /** surplusStandard as sent - 0 would turn the check off on-device, so an unknown level becomes Moderate. */
    public static function surplusStandardForWire(int $standard): int
    {
        return isset(self::SURPLUS_THRESHOLDS[$standard]) ? $standard : 2;
    }
}
