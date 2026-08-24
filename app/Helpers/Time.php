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

    /**
     * One 'latest[]' entry per schedule item - each item's own next future
     * occurrence, not a globally-sorted "soonest N across everything" list.
     * Corrected 2026-08-23 against a real capture from the actual Petkit
     * app/cloud (real product key in the topic, not localkit's own): a
     * 4-times-a-day schedule (00:00/06:00/12:00/18:00) produced exactly 4
     * 'latest[]' entries, one per item, each 't' matching that specific
     * item's own next occurrence to the second. This ruled out every earlier
     * take(N) guess (3, then 2) - there's no fixed cap at all, it's simply
     * "however many items the schedule has, over all its groups".
     */
    public static function calculateLatest(array $schedules)
    {
        $current = Carbon::now();
        $latest = [];

        foreach ($schedules as $schedule) {
            $days = explode(',', $schedule['re']);

            foreach ($schedule['it'] as $item) {
                [$hour, $minute] = explode(':', self::toTimeFromSeconds($item['t']));

                // This item's own soonest future occurrence across all the
                // weekdays its 're' mask matches - not the soonest across
                // the whole schedule.
                $next = null;
                foreach ($days as $day) {
                    $date = $current->copy()->next(self::$days[$day]);
                    if (self::$days[$day] == $current->dayOfWeek) {
                        $date = $current->copy();
                    }
                    $date->setTime((int) $hour, (int) $minute);

                    // Today's occurrence of this weekday already passed -
                    // roll to next week's, rather than dropping this weekday
                    // as a candidate entirely (another weekday in the same
                    // 're' mask might still be closer than that, so both
                    // stay in the running below).
                    if ($date->lessThanOrEqualTo($current)) {
                        $date->addWeek();
                    }

                    if ($next === null || $date->lessThan($next)) {
                        $next = $date;
                    }
                }

                if ($next === null) {
                    continue;
                }

                // Single-hopper devices key the dispensed amount as 'a'; dual-hopper
                // devices (e.g. D4SH) split it into 'a1'/'a2' - pass through whichever
                // of these the schedule item actually carries.
                $amounts = array_map(fn ($amount) => (int)$amount, Arr::only($item, ['a', 'a1', 'a2']));

                $latest[] = [
                    ...$amounts,
                    // Reverted 2026-08-24: the 's%d_%d' shortening here was
                    // the 'latest' counterpart of the same now-disproven
                    // hypothesis reverted in the per-device UI.php files (see
                    // their comments) - a live side-by-side test against the
                    // real app showed the real app sending the full 16-byte
                    // unterminated 's_%d_%d' form and firing fine on it,
                    // while localkit's schedule item id shortening (the
                    // actual cause) silently failed to fire. Reverting this
                    // matching 'latest' id shortening too, on the same
                    // evidence, even though it wasn't independently tested.
                    'id' => sprintf('s_%d_%d', $next->format('Ymd'), $item['t']),
                    // cJSON's valueint is fine with a float-looking JSON number, but
                    // schedule.md's own §3d warning (wrong JSON type is worse than a
                    // missing field) is reason enough to not rely on that leniency -
                    // round() returns a float, so cast explicitly to a genuine int.
                    // max(0, ...) guards the edge where $next lands under 1s away -
                    // diffInSeconds() truncates that to 0 and the -1 would otherwise
                    // send a negative t, which schedule.md §3b documents as hitting a
                    // different (verbatim-store) on-device code path than a
                    // non-negative one for 'latest' items - not what's intended here.
                    't' => max(0, (int) round($current->diffInSeconds($next, true) - 1))
                ];
            }
        }

        // HandlesFeederSchedule::toFeed()/toFeedGet() take nextTick as the
        // *last* entry here (the farthest-out occurrence, per schedule.md
        // §4h's live-capture evidence) - sort ascending by 't' so that holds
        // regardless of the order items happen to be stored in, the same
        // guarantee the old ksort()-before-take(N) version gave for free.
        usort($latest, fn(array $a, array $b) => $a['t'] <=> $b['t']);

        return $latest;
    }

    /**
     * The device's on-device parser (pk_schmg_parse_schedule, per schedule.md
     * §3c) copies a schedule group's 're' verbatim into a 20-byte stack
     * buffer via strcpy with no length check, then scans all 20 buffer
     * positions independently, setting a bit for each '1'-'7' digit found -
     * non-digit characters (e.g. the comma our stored/editable representation
     * uses) are just skipped in place, not a parse-stopping delimiter. So
     * "1,2,3" and "123" produce the identical mask on-device either way -
     * but the comma is kept here (only genuinely invalid characters are
     * stripped) since it's the readable form the app itself sends and what
     * shows up in captures/logs; a bare "1267" is correct but confusing to
     * read back. The 20-char buffer is still the hard constraint - a 're' at
     * or over ~20 chars overflows that stack buffer on-device (crash risk,
     * not just a rejected write) - so length stays capped regardless.
     */
    public static function toWireRepeatDays(string $re): string
    {
        return substr(preg_replace('/[^1-7,]/', '', $re), 0, 19);
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
