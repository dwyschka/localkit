<?php

namespace App\Filament\StateCasts;

use App\Helpers\Time;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/**
 * Stored seconds-since-midnight <-> the "H:i" a time input edits.
 */
class SecondsTimeStateCast implements StateCast
{
    public function get(mixed $state): ?int
    {
        if (blank($state)) {
            return null;
        }

        if (is_numeric($state)) {
            return (int) $state;
        }

        return Time::toSeconds($state);
    }

    public function set(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if (is_numeric($state)) {
            return Time::toTimeFromSeconds((int) $state);
        }

        return (string) $state;
    }
}
