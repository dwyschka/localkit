<?php

namespace App\Petkit\BluetoothDevices\K3;
use Filament\Forms;

class UI
{

    public function formFields(): array {
        return [
            Forms\Components\Section::make('K3 Settings')->schema([
                Forms\Components\ViewField::make('k3Warning')
                    ->columnSpanFull()
                    ->view('filament.forms.warning')
                    ->viewData(['message' => 'Its possible to manipulate the values, but its not verified']),

                Forms\Components\KeyValue::make('configuration.settings.standard')
                    ->deletable(false)
                    ->addable(false)
                    ->label('Standard'),
                Forms\Components\TextInput::make('configuration.settings.lightness')->label('Lightness'),
                Forms\Components\TextInput::make('configuration.settings.lowVoltage')->label('Low Voltage'),
                Forms\Components\TextInput::make('configuration.settings.refreshTotalTime')->label('Refresh Total Time'),
                Forms\Components\TextInput::make('configuration.settings.singleRefreshTime')->label('Single Refresh Time'),
                Forms\Components\TextInput::make('configuration.settings.singleLightTime')->label('Single Light Time'),
            ]),
        ];
    }
}
