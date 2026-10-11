<?php

namespace App\Petkit\Devices\Unknown;

use App\Models\Device;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

/**
 * The edit form for a device type Localkit has no implementation for: an
 * explanation of what does and does not work, plus the one field that is
 * useful anyway (its address, since nothing reports it for us here).
 */
class UI
{
    public function formFields(): array
    {
        return [
            Section::make('Unsupported device')
                ->description('Localkit does not have an implementation for this device type.')
                ->schema([
                    Placeholder::make('unsupported_notice')
                        ->hiddenLabel()
                        ->content(fn (Device $record): HtmlString => new HtmlString(sprintf(
                            '<p>This device reported the type <strong>%s</strong>, which Localkit does not know.'
                            . ' It is registered, it keeps its id and secret, and its sign-up, server info and'
                            . ' heartbeat are answered - so it stays happily connected to this server.</p>'
                            . '<p class="mt-2">What it cannot do is be controlled: there are no actions, no settings'
                            . ' and no state reporting for an unknown type. Everything it publishes over MQTT is'
                            . ' logged to <code>storage/logs/localkit_unknown_topics.log</code>, which is where to'
                            . ' start if you want to add support for it.</p>',
                            e($record->device_type ?: 'unknown'),
                        ))),
                ]),

            Section::make('Stats')->schema([
                TextInput::make('configuration.states.ipAddress')
                    ->label('IP Address')
                    // Nothing parses this device's state reports, so unlike on
                    // an implemented device it is never filled in or corrected
                    // automatically - whatever is typed here is all there is.
                    ->helperText('Not reported for an unknown device type - set it by hand if you need it.')
                    ->ip()
                    ->placeholder('192.168.1.42'),
            ]),
        ];
    }
}
