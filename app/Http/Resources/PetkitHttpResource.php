<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PetkitHttpResource extends JsonResource
{
    public static $wrap = 'result';

    public function toResponse($request)
    {
        $response = response()->json(
            [
                self::$wrap => $this->resolve($request)
            ],
            200,
            [],
            JSON_UNESCAPED_SLASHES|JSON_PRESERVE_ZERO_FRACTION
        );

        Log::info('PETKITHTTP', ['content' => $response->getContent()]);

        return $response->header('Content-Type', 'application/json;charset=utf-8')
            ->header('Content-Length', strlen($response->getContent()));
    }
}
