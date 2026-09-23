<?php

namespace App\Filament\Forms\Components;

use App\Filament\StateCasts\SecondsTimeStateCast;
use Filament\Forms\Components\TimePicker;

/**
 * A TimePicker for a value stored as seconds since midnight - see
 * MinutesTimePicker for why the default cast has to be replaced.
 */
class SecondsTimePicker extends TimePicker
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seconds(false);
    }

    public function getDefaultStateCasts(): array
    {
        return [app(SecondsTimeStateCast::class)];
    }
}
