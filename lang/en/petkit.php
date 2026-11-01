<?php

return [
    'unknown' => 'Unknown',
    'history' => [
        'in_use' => 'In use (:weight kg) for :duration seconds',
        // No weight sensor on some devices (e.g. Purobot Crystal) - falls
        // back to this when parameters carry no pet_weight.
        'in_use_no_weight' => 'In use for :duration seconds',
        'in_use_title' => 'Cat pooping',
        'cleaning' => 'Manual Cleaning',
        'cleaning_title' => 'Manual Cleaning',
        'maintenance_title' => 'Maintenance',
        'maintenance' => 'Maintenance for :duration seconds',
        'working_title' => 'Feeding',
        // History::createFeedMessage() - wording from the Petkit app's D4SH feed record.
        'feed' => 'Dispensed :amount portion(s) - :reason',
        'feed_skipped_surplus' => 'Dispensing paused by Surplus Food Control: the camera saw leftover food in the bowl. Turn Surplus Food Control off if scheduled feeds should always dispense.',
        'feed_unknown' => 'unknown outcome (result :result, error code :err_code)',
        'error' => 'Error',
        'error_title' => 'Error',
        'eat_title' => 'Eating',
        'eat' => 'Ate for :duration seconds',
        'detect_title' => 'Motion detected',
        'detect' => 'Pet detected near the camera',
        'detect_count' => ':count pet(s) detected near the camera',
        'drink_title' => 'Drinking',
        'drink' => 'Drank for :duration seconds',
    ],
    'error' => [
        'hallT' =>'The lid is not closed',
        'full' =>'The bin is full',
        'food_empty' => 'The food bin is empty',
        'wastebin_full' => 'The waste water tank is full',
        'water_tank_empty' => 'The fresh water tank is empty',
        'valve_error' => 'The lift valve has a fault',
        'pump_malfunction' => 'The circulation pump has a fault',
        'heater_malfunction' => 'The heater has a fault',
        'heater_low_water' => 'The heater is protecting against low water',
        'ota_failed' => 'The firmware update failed',
        'ota_task_failed' => 'The firmware update could not be started (device out of resources)',

        // Feeder (D4/D4S/D4SH/D3) error_start `err` codes - these match the
        // field names of the device's own state.err struct 1:1 ("blk_d"
        // confirmed via a live D4SH error_start capture on 2026-08-27; the
        // rest are inferred from that same struct's naming, not
        // independently confirmed against firmware disassembly).
        'DC' => 'A power supply fault was detected',
        'sys' => 'A system fault was detected',
        'rtc_c' => 'The clock calibration failed',
        'moto' => 'The feeding motor has a fault',
        'blk_f' => 'The hopper is blocked',
        'blk_d' => 'The dispenser is blocked',
        'camera' => 'The camera has a fault',
        'serial' => 'A serial communication fault was detected',
    ],
    // Feeder feed_over `result` values (Petkit app 13.4.1, D4SH record list).
    // 8 (surplus food control) is handled separately, see History::createFeedMessage().
    'feed_result' => [
        0 => 'completed',
        1 => 'dispensing was manually halted',
        2 => 'dispensing was manually halted',
        4 => 'insufficient food in hopper 1, please refill it',
        5 => 'insufficient food in hopper 2, please refill it',
        6 => 'insufficient food in both hoppers, please refill them',
        9 => 'hopper 1 ran empty, please refill it',
        10 => 'hopper 2 ran empty, please refill it',
        11 => 'both hoppers ran empty, please refill them',
        12 => 'hopper 1 insufficient, hopper 2 ran empty',
        13 => 'hopper 1 ran empty, hopper 2 insufficient',
    ],
    // err_code for result 3 (dispensing failed), plus err_code 8 on any result.
    'feed_error' => [
        1 => 'dispensing failed: power failure',
        2 => 'dispensing failed: system malfunction',
        3 => 'dispensing failed: food is jammed',
        4 => 'dispensing failed: system malfunction',
        5 => 'dispensing failed: low battery',
        8 => 'food has piled up in the dispenser, please clean it',
    ],
    // err_code for result 7 (dispensing cancelled).
    'feed_cancel' => [
        6 => 'cancelled: firmware upgrading',
        7 => 'cancelled: already dispensing',
    ],
];
