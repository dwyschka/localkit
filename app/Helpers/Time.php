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
                    // cJSON's valueint is fine with a float-looking JSON number, but
                    // schedule.md's own §3d warning (wrong JSON type is worse than a
                    // missing field) is reason enough to not rely on that leniency -
                    // round() returns a float, so cast explicitly to a genuine int.
                    't' => (int) round($current->diffInSeconds($date, true) - 1)
                ];
            })
            ->values()->toArray();

        return $nextSchedule;
    }

    /**
     * The device's on-device parser (pk_schmg_parse_schedule, per schedule.md
     * §3c) copies a schedule group's 're' verbatim into a 20-byte stack
     * buffer via strcpy with no length check, then scans all 20 buffer
     * positions independently, setting a bit for each '1'-'7' digit found -
     * non-digit characters (e.g. the comma our stored/editable representation
     * uses) are just skipped in place, not a parse-stopping delimiter. So
     * "1,2,3" and "123" already produce the identical mask; stripping the
     * comma here isn't required for correctness, only for staying well under
     * the 20-char buffer - a 're' at or over ~20 chars overflows that stack
     * buffer on-device (crash risk, not just a rejected write). Our source is
     * always at most the 7 distinct weekday digits, but the length is capped
     * defensively in case that ever changes upstream.
     */
    public static function toWireRepeatDays(string $re): string
    {
        return substr(preg_replace('/[^1-7]/', '', $re), 0, 19);
    }

    /**
     * schedule.md §3d: the device reads 'id'/'re' via cJSON's valuestring
     * (no NULL check - a JSON number in that slot instead of a string is a
     * NULL-pointer deref, i.e. a crash, not a graceful rejection) and
     * 'a'/'a1'/'a2'/'t' via valueint (a JSON string there just silently
     * reads as 0, no error). Storage already casts these correctly on save,
     * but that's a UI-layer guarantee, not a wire-format one - this
     * re-asserts the right native JSON type for every field right before a
     * schedule group is serialized onto the wire, so a stray untyped value
     * from any other write path can't reach the device as one of these.
     */
    public static function normalizeScheduleGroupForWire(array $schedule): array
    {
        return [
            ...$schedule,
            're' => self::toWireRepeatDays((string) $schedule['re']),
            'it' => array_map(fn(array $item) => [
                ...$item,
                'id' => (string) $item['id'],
                ...(array_key_exists('a', $item) ? ['a' => (int) $item['a']] : []),
                ...(array_key_exists('a1', $item) ? ['a1' => (int) $item['a1']] : []),
                ...(array_key_exists('a2', $item) ? ['a2' => (int) $item['a2']] : []),
                't' => (int) $item['t'],
            ], $schedule['it']),
        ];
    }

    public static function toTimeFromSeconds(int $seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $time = sprintf('%02d:%02d', $hours, $minutes);
        return $time;
    }
}
