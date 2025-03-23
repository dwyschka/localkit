<?php

namespace App\Http\Controllers\Petkit\T4;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DevEventReportController extends Controller
{
    public function __invoke(string $deviceType, Request $request)
    {
        return $this->proxy($request);

    }
}
