<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('{deviceType}')->group(function () {
    Route::post('dev_signup', \App\Http\Controllers\Petkit\T4\DevSignupController::class);
    Route::post('dev_iot_device_info', \App\Http\Controllers\Petkit\T4\DevIotDeviceInfoController::class);
    Route::post('dev_ota_check', \App\Http\Controllers\Petkit\T4\DevOtaCheckController::class);
    Route::post('dev_serverinfo', \App\Http\Controllers\Petkit\T4\DevServerinfoController::class);
    Route::post('dev_multi_config', \App\Http\Controllers\Petkit\T4\DevMultiConfigController::class);
    Route::post('dev_ble_device', \App\Http\Controllers\Petkit\T4\DevBleDeviceController::class);
    Route::post('dev_schedule_get', \App\Http\Controllers\Petkit\T4\DevScheduleGetController::class);
    Route::post('dev_device_info', \App\Http\Controllers\Petkit\T4\DevDeviceInfoController::class);

    //2do
    Route::post('dev_state_report', \App\Http\Controllers\Petkit\T4\DevStateReportController::class);
    Route::post('dev_event_report', \App\Http\Controllers\Petkit\T4\DevEventReportController::class);
});


    Route::post('poll/{slug}/heartbeat', \App\Http\Controllers\Petkit\T4\HeartbeatController::class);

    Route::prefix('api')->middleware(['api'])->group(function () {
       Route::get('topics/{serialNumber}', \App\Http\Controllers\Api\TopicController::class);
       Route::post('connected/{serialNumber}', \App\Http\Controllers\Api\DeviceConnectedController::class);
    });
