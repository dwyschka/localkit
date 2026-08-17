<?php

namespace App\MQTT;

use App\Http\Resources\MQTT\AddWaterReset;
use App\Models\Device;

/**
 * `add_water_reset` from IMPLEMENT/w7h_actions.csv: "Resets the add-water
 * switch/counter state (see addWaterSwitch)". The firmware analysis only
 * confirmed the command name and topic/method-naming convention (matching
 * the already-verified `thing.service.start`/`thing.service.connect` shape),
 * not the exact single parameter it reads ("(unlabeled - single
 * GetObjectItem call)") - `add_water_reset_action` follows the `<name>_action`
 * convention used by every other confirmed action in that CSV as the best
 * available guess.
 */
class AddWaterResetMessage
{
    public static function send(Device $device): AnswerDTO {
        return new AnswerDTO([
            'topic' => sprintf('/sys/%s/%s/thing/service/add_water_reset', $device->productKey(), $device->deviceName()),
            'message' => AddWaterReset::make($device),
        ]);
    }
}
