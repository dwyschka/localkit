<?php

namespace App\Petkit\BluetoothDevices\K3;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\KeyValue;
use Filament\Forms;

class UI
{

    public function formFields(): array
    {
        return [
            Section::make('Consumables')->columns(2)->schema([
                TextInput::make('configuration.consumables.liquid')
                    ->label('Liquid')
                    ->disabled()
                    ->columnSpan('half'),
                TextInput::make('configuration.consumables.battery')
                    ->label('Battery')
                    ->disabled()
                    ->columnSpan('half'),
            ]),
            Section::make('K3 Settings')->schema([

                ViewField::make('k3Warning')
                    ->columnSpanFull()
                    ->view('filament.forms.warning')
                    ->viewData(['message' => 'Its possible to manipulate the values, but its not verified']),

                KeyValue::make('configuration.settings.standard')
                    ->deletable(false)
                    ->addable(false)
                    ->label('Standard'),
                TextInput::make('configuration.settings.lightness')->label('Lightness'),
                TextInput::make('configuration.settings.lowVoltage')->label('Low Voltage'),
                TextInput::make('configuration.settings.refreshTotalTime')->label('Refresh Total Time'),
                TextInput::make('configuration.settings.singleRefreshTime')->label('Single Refresh Time'),
                TextInput::make('configuration.settings.singleLightTime')->label('Single Light Time'),
            ]),
        ];
    }
}
