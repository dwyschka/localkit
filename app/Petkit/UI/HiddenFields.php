<?php

namespace App\Petkit\UI;

use App\Models\Device;
use Filament\Forms\Components\Hidden;

trait HiddenFields
{

    public function hiddenFields(Device $device): array
    {
        $states = $device->definition()->configurationDefinition()->toArray()['states'] ?? [];
        $fields = [];

        foreach($states as $key => $state) {
            $key = sprintf('configuration.states.%s', $key);

            $fields[] = Hidden::make($key)->default($state);
        }

        return $fields;

    }

}
