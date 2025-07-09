<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

class Time
{

    public static function toTimeFromSeconds(int $seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $time = sprintf('%02d:%02d', $hours, $minutes);
        return $time;
    }

    public static function toTimeFromMinutes(int $minutes) {
        if ($minutes < 0 || $minutes >= 1440) {
            throw new InvalidArgumentException('Minutes must be between 0 and 1439 (24 hours)');
        }

        $hours = intval($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }


    public static function toSeconds(string $time) {
        $time = Carbon::parse($time);
        return $time->hour * 3600 + $time->minute * 60 + $time->second;
    }

    public static function toMinutes(string $time) {
        $time = Carbon::createFromFormat('H:i', $time);
        return ($time->hour * 60) + $time->minute;
    }

}
