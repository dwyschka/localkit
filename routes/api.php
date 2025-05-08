<?php

use Illuminate\Support\Facades\Route;

Route::prefix('{deviceType}')->group(function () {
    Route::post('dev_signup', \App\Http\Controllers\Petkit\DevSignupController::class);
    Route::post('dev_iot_device_info', \App\Http\Controllers\Petkit\DevIotDeviceInfoController::class);
    Route::post('dev_ota_check', \App\Http\Controllers\Petkit\DevOtaCheckController::class);
    Route::post('dev_ota_start', \App\Http\Controllers\Petkit\DevOtaController::class);
    Route::post('dev_ota_complete', \App\Http\Controllers\Petkit\DevOtaController::class);
    Route::post('dev_serverinfo', \App\Http\Controllers\Petkit\DevServerinfoController::class);
    Route::post('dev_multi_config', \App\Http\Controllers\Petkit\DevMultiConfigController::class);
    Route::post('dev_ble_device', \App\Http\Controllers\Petkit\DevBleDeviceController::class);
    Route::post('dev_schedule_get', \App\Http\Controllers\Petkit\DevScheduleGetController::class);
    Route::post('dev_device_info', \App\Http\Controllers\Petkit\DevDeviceInfoController::class);

    //2do
    Route::post('dev_state_report', \App\Http\Controllers\Petkit\DevStateReportController::class);
    Route::post('dev_event_report', \App\Http\Controllers\Petkit\DevEventReportController::class);
});


    Route::post('poll/{slug}/heartbeat', \App\Http\Controllers\Petkit\HeartbeatController::class);

    Route::prefix('api')->middleware(['api'])->group(function () {
       Route::get('topics/{serialNumber}', \App\Http\Controllers\Api\TopicController::class);
       Route::post('connected/{serialNumber}', \App\Http\Controllers\Api\DeviceConnectedController::class);
    });
