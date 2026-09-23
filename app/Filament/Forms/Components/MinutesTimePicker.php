<?php

namespace App\Filament\Forms\Components;

use App\Filament\StateCasts\MinutesTimeStateCast;
use Filament\Forms\Components\TimePicker;

/**
 * A TimePicker for a value stored as minutes since midnight.
 *
 * TimePicker's own DateTimeStateCast always runs before any
 * formatStateUsing() - and ->stateCast() only appends to it rather than
 * replacing it - so it parses the raw stored integer as a date/time first
 * (510 -> "01:08" during BST), leaving nothing for a minutes->time
 * conversion to work with. Replacing the default cast is the only way to
 * see the raw value.
 */
class MinutesTimePicker extends TimePicker
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seconds(false);
    }

    public function getDefaultStateCasts(): array
    {
        return [app(MinutesTimeStateCast::class)];
    }
}
