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
                    ->dehydrated()
                    ->disabled()
                    ->columnSpan('full'),
            ]),
            Forms\Components\Section::make('States')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.states.powerStatus')
                    ->disabled()
                    ->dehydrated()
                    ->label('Power'),
                Forms\Components\Toggle::make('configuration.states.runningStatus')
                    ->disabled()
                    ->dehydrated()
                    ->label('Running'),
                Forms\Components\Toggle::make('configuration.states.dndState')
                    ->disabled()
                    ->dehydrated()
                    ->label('Do not Disturb'),

            ]),
            Forms\Components\Section::make('Errors')->columns(2)->schema([

                Forms\Components\Toggle::make('configuration.states.warningBreakdown')
                    ->disabled()
                    ->dehydrated()
                    ->label('Breakdown Error'),
                Forms\Components\Toggle::make('configuration.states.warningWaterMissing')
                    ->disabled()
                    ->dehydrated()
                    ->label('Water Missing Error'),
                Forms\Components\Toggle::make('configuration.states.warningFilter')
                    ->disabled()
                    ->dehydrated()
                    ->label('Filter error'),
            ])


        ];
    }
}
