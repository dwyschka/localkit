<?php

use App\Http\Controllers\Petkit\RepositoryController;
use Illuminate\Support\Facades\Route;


if (config('localkit.firmware_proxy')) {
    Route::any('repository/{path}', RepositoryController::class)->where('path', '.*');
}
