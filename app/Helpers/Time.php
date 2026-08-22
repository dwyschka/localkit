<?php

namespace App\Helpers;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class Time
{
    public static array $days = [
        1 => CarbonInterface::SUNDAY,
        2 => CarbonInterface::MONDAY,
        3 => CarbonInterface::TUESDAY,
        4 => CarbonInterface::WEDNESDAY,
        5 => CarbonInterface::THURSDAY,
        6 => CarbonInterface::FRIDAY,
        7 => CarbonInterface::SATURDAY,
    ];

    public static function toTimeFromMinutes(int $minutes)
    {
        if($minutes === 1440) {
            $minutes = 1439;
        }
        if ($minutes < 0 || $minutes >= 1441) {
            throw new InvalidArgumentException('Minutes must be between 0 and 1439 (24 hours)');
        }

        $hours = intval($minutes / 60);
        $mins = $minutes % 60;

        return sprintf('%02d:%02d', $hours, $mins);
    }

    public static function toSeconds(string $time)
    {
        $time = Carbon::parse($time);
        return $time->hour * 3600 + $time->minute * 60 + $time->second;
    }

    public static function toMinutes(string $time)
    {
        $time = Carbon::createFromFormat('H:i', $time);
        return ($time->hour * 60) + $time->minute;
    }

    public static function calculateLatest(array $schedules)
    {
        $nextSchedule = [];
        foreach ($schedules as $schedule) {
            $days = explode(',', $schedule['re']);

            foreach ($schedule['it'] as $interval) {

                foreach ($days as $day) {
                    [$hour, $minute] = explode(':', self::toTimeFromSeconds($interval['t']));

                    $date = Carbon::now()->next(self::$days[$day]);
                    if (self::$days[$day] == Carbon::now()->dayOfWeek) {
                        $date = Carbon::now();
                    }
                    $nextSchedule[$date->setTime($hour, $minute)->timestamp] = $interval;
                }
            }
        }

        ksort($nextSchedule);
        $current = Carbon::now();

        return collect($nextSchedule)
            ->filter(fn($item, $key) => $key > $current->timestamp)
            ->take(3)
            ->map(function (array $item, int $key) use ($current){

                $date = Carbon::createFromTimestamp($key);

                // Single-hopper devices key the dispensed amount as 'a'; dual-hopper
                // devices (e.g. D4SH) split it into 'a1'/'a2' - pass through whichever
                // of these the schedule item actually carries.
                $amounts = array_map(fn ($amount) => (int)$amount, Arr::only($item, ['a', 'a1', 'a2']));

                return [
                    ...$amounts,
                    'id' => sprintf('s_%d_%d', $date->format('Ymd'), $item['t']),
                    't' => round($current->diffInSeconds($date, true) - 1)
                ];
            })
            ->values()->toArray();

        return $nextSchedule;
    }

    /**
     * The device's on-device parser (pk_schmg_parse_schedule, per schedule.md)
     * reads a schedule group's 're' one character at a time and sets a bit for
     * each '1'-'7' digit - the first character outside that range (e.g. the
     * comma our stored/editable representation uses) truncates parsing for
     * the rest of the string instead of being skipped as a separator. Storage
     * and the Filament form keep the comma-separated form for readability;
     * this strips it down to the bare digit run the firmware actually expects
     * right before a schedule is serialized onto the wire (MQTT 'feed' or the
     * HTTP dev_feed_get response - both go through the same parser).
     */
    public static function toWireRepeatDays(string $re): string
    {
        return preg_replace('/[^1-7]/', '', $re);
    }

    public static function toTimeFromSeconds(int $seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $time = sprintf('%02d:%02d', $hours, $minutes);
        return $time;
    }
}
