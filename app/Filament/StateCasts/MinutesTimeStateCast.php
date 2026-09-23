<?php

namespace App\Filament\StateCasts;

use App\Helpers\Time;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Illuminate\Support\Carbon;

/**
 * Stored minutes-since-midnight <-> the "H:i" a time input edits.
 */
class MinutesTimeStateCast implements StateCast
{
    public function get(mixed $state): ?int
    {
        if (blank($state)) {
            return null;
        }

        if (is_numeric($state)) {
            return (int) $state;
        }

        $time = Carbon::parse($state);

        return ($time->hour * 60) + $time->minute;
    }

    public function set(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if (is_numeric($state)) {
            return Time::toTimeFromMinutes((int) $state);
        }

        return (string) $state;
    }
}
