<?php

namespace App\Http\Controllers\Petkit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DevStateReportController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {
        return $this->proxy($request);

    }
}
