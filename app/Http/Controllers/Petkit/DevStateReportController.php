<?php

namespace App\Http\Controllers\Petkit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DevStateReportController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {
        Log::info('Dev State REport', ['params' => $request->all()]);
        return $this->proxy($request);

    }
}
