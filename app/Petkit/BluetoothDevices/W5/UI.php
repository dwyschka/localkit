<?php

namespace App\Petkit\BluetoothDevices\W5;

use Filament\Forms;

class UI
{

    public function formFields(): array
    {
        return [
            Forms\Components\Section::make('Consumables')->columns(2)->schema([
                Forms\Components\TextInput::make('configuration.consumables.filterPercentage')
                    ->label('Filter %')
                    ->disabled()
                    ->columnSpan('full'),
            ]),
            Forms\Components\Section::make('States')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.states.powerStatus')
                    ->disabled()
                    ->label('Power'),
                Forms\Components\Toggle::make('configuration.states.runningStatus')
                    ->disabled()
                    ->label('Running'),
                Forms\Components\Toggle::make('configuration.states.dndState')
                    ->disabled()
                    ->label('Do not Disturb'),

            ]),
            Forms\Components\Section::make('Errors')->columns(2)->schema([

                Forms\Components\Toggle::make('configuration.states.warningBreakdown')
                    ->disabled()
                    ->label('Breakdown Error'),
                Forms\Components\Toggle::make('configuration.states.warningWaterMissing')
                    ->disabled()
                    ->label('Water Missing Error'),
                Forms\Components\Toggle::make('configuration.states.warningFilter')
                    ->disabled()
                    ->label('Filter error'),
            ])


        ];
    }
}
