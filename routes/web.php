<?php

use App\Http\Controllers\Petkit\RepositoryController;
use Illuminate\Support\Facades\Route;


Route::any('repository/{path}', RepositoryController::class)->where('path', '.*');
