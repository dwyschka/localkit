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
use App\Http\Controllers\Petkit\DevK3DeviceInfoController;
use App\Http\Controllers\Petkit\DevMultiConfigController;
use App\Http\Controllers\Petkit\DevOnlyIotDeviceInfoController;
use App\Http\Controllers\Petkit\DevOssStsInfoNewV2Controller;
use App\Http\Controllers\Petkit\DevOtaCheckController;
use App\Http\Controllers\Petkit\DevOtaCompleteController;
use App\Http\Controllers\Petkit\DevOtaStartController;
use App\Http\Controllers\Petkit\DevScheduleGetController;
use App\Http\Controllers\Petkit\DevServerinfoController;
use App\Http\Controllers\Petkit\DevSignupController;
use App\Http\Controllers\Petkit\DevSoundGetController;
use App\Http\Controllers\Petkit\DevStateReportController;
use App\Http\Controllers\Petkit\DevSyncTimeController;
use App\Http\Controllers\Petkit\DevVideoDeviceInfoController;
use App\Http\Controllers\Petkit\HeartbeatController;
use App\Http\Controllers\Petkit\RepositoryController;
use App\Http\Controllers\Petkit\DevSandtrayAuthController;
use \App\Http\Controllers\Petkit\DevUploadLogTokenController;

use Illuminate\Support\Facades\Route;

Route::prefix('{deviceType}')->group(function () {
    Route::post('dev_signup', DevSignupController::class);
    Route::get('dev_signup', DevSignupController::class);

    Route::post('dev_iot_device_info', DevIotDeviceInfoController::class);
    Route::post('dev_ota_check', DevOtaCheckController::class);
    Route::post('dev_ota_start', DevOtaStartController::class);
    Route::post('dev_ota_complete', DevOtaCompleteController::class);
    Route::post('dev_serverinfo', DevServerinfoController::class);
    Route::post('dev_multi_config', DevMultiConfigController::class);
    Route::post('dev_ble_device', DevBleDeviceController::class);
    Route::post('dev_schedule_get', DevScheduleGetController::class);
    Route::post('dev_device_info', DevDeviceInfoController::class);
    Route::post('dev_k3_device_info', DevK3DeviceInfoController::class);

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
    Route::get('dev_schedule_get', DevScheduleGetController::class);

    //Sandtray t7
    Route::get('dev_sand_tray_auth', DevSandtrayAuthController::class);
    Route::get('dev_upload_log_token', DevUploadLogTokenController::class);


});


    Route::match(['get', 'post'], 'poll/{slug}/heartbeat', HeartbeatController::class)
        ->middleware(\App\Http\Middleware\LogDeviceHttpRequests::class);


    Route::prefix('api')->middleware(['api'])->group(function () {
       Route::get('topics/{serialNumber}', TopicController::class);
       Route::post('connected/{serialNumber}', DeviceConnectedController::class);
    });


Route::any('{fallback}', DevFallbackController::class)->where('fallback', '.*');
