<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PetkitHeader
{

    public static function petkitId($header): string
    {
        $parameters = self::parse($header);
        return $parameters['id'];
    }

    public static function deviceType($header): string
    {
        $parameters = self::parse($header);
        return Str::lower($parameters['type']);
    }

    protected static function parse(string $header): array
    {

        $output = [];
        parse_str($header, $output);

        return $output;
    }
}
