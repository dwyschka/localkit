<?php

use App\Http\Controllers\Api\DeviceConnectedController;
use App\Http\Controllers\Api\TopicController;
use App\Http\Controllers\Petkit\DevAttireOverController;
use App\Http\Controllers\Petkit\DevBleDeviceController;
use App\Http\Controllers\Petkit\DevDeviceInfoController;
use App\Http\Controllers\Petkit\DevEventReportController;
use App\Http\Controllers\Petkit\DevFallbackController;
use App\Http\Controllers\Petkit\DevFeedGetController;
use App\Http\Controllers\Petkit\DevIotDeviceInfoController;
use App\Http\Controllers\Petkit\DevMultiConfigController;
use App\Http\Controllers\Petkit\DevOnlyIotDeviceInfoController;
use App\Http\Controllers\Petkit\DevOssStsInfoNewV2Controller;
use App\Http\Controllers\Petkit\DevOtaCheckController;
use App\Http\Controllers\Petkit\DevOtaController;
use App\Http\Controllers\Petkit\DevScheduleGetController;
use App\Http\Controllers\Petkit\DevServerinfoController;
use App\Http\Controllers\Petkit\DevSignupController;
use App\Http\Controllers\Petkit\DevSoundGetController;
use App\Http\Controllers\Petkit\DevStateReportController;
use App\Http\Controllers\Petkit\DevSyncTimeController;
use App\Http\Controllers\Petkit\DevVideoDeviceInfoController;
use App\Http\Controllers\Petkit\HeartbeatController;
use App\Http\Controllers\Petkit\RepositoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('{deviceType}')->group(function () {
    Route::post('dev_signup', DevSignupController::class);
    Route::get('dev_signup', DevSignupController::class);

    Route::post('dev_iot_device_info', DevIotDeviceInfoController::class);
    Route::post('dev_ota_check', DevOtaCheckController::class);
    Route::post('dev_ota_start', DevOtaController::class);
    Route::post('dev_ota_complete', DevOtaController::class);
    Route::post('dev_serverinfo', DevServerinfoController::class);
    Route::post('dev_multi_config', DevMultiConfigController::class);
    Route::post('dev_ble_device', DevBleDeviceController::class);
    Route::post('dev_schedule_get', DevScheduleGetController::class);
    Route::post('dev_device_info', DevDeviceInfoController::class);

    //2do
    Route::post('dev_state_report', DevStateReportController::class);
    Route::post('dev_event_report', DevEventReportController::class);

    //embedded linux device
    Route::get('dev_syncTime', DevSyncTimeController::class);
    Route::get('dev_only_iot_device_info_v2', DevOnlyIotDeviceInfoController::class);
    Route::get('dev_only_iot_device_info', DevOnlyIotDeviceInfoController::class);
    Route::get('dev_video_device_info', DevVideoDeviceInfoController::class);
    Route::get('dev_oss_sts_info_new_v2', DevOssStsInfoNewV2Controller::class);
    Route::get('dev_multi_config', DevMultiConfigController::class);
    Route::get('dev_ble_device', DevBleDeviceController::class);
    Route::get('dev_sound_get', DevSoundGetController::class);
    Route::get('dev_device_info', DevDeviceInfoController::class);
    Route::get('dev_attire_over', DevAttireOverController::class);
    Route::get('dev_feed_get', DevFeedGetController::class);
    Route::get('dev_serverinfo', DevServerinfoController::class);


});


    Route::post('poll/{slug}/heartbeat', HeartbeatController::class);
    Route::get('poll/{slug}/heartbeat', HeartbeatController::class);


    Route::prefix('api')->middleware(['api'])->group(function () {
       Route::get('topics/{serialNumber}', TopicController::class);
       Route::post('connected/{serialNumber}', DeviceConnectedController::class);
    });


Route::any('{fallback}', DevFallbackController::class)->where('fallback', '.*');
