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
    ]
];
