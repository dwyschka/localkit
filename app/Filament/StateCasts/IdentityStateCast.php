<?php

namespace App\Filament\StateCasts;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/**
 * Passes state through unchanged. Attach via ->stateCast() to a field to
 * opt out of a component's built-in default state cast (e.g. CheckboxList's
 * OptionsArrayStateCast, which discards a comma-separated string because it
 * isn't valid JSON) while keeping a custom formatStateUsing()/
 * dehydrateStateUsing() pair in full control of the raw <-> form state
 * conversion.
 */
class IdentityStateCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return $state;
    }

    public function set(mixed $state): mixed
    {
        return $state;
    }
}
