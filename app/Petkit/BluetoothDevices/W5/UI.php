<?php

namespace App\Petkit\BluetoothDevices\W5;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms;

class UI
{

    public function formFields(): array
    {
        return [
            Section::make('Consumables')->columns(2)->schema([
                TextInput::make('configuration.consumables.filterPercentage')
                    ->label('Filter %')
                    ->dehydrated()
                    ->disabled()
                    ->columnSpan('full'),
            ]),
            Section::make('States')->columns(2)->schema([
                Toggle::make('configuration.states.powerStatus')
                    ->disabled()
                    ->dehydrated()
                    ->label('Power'),
                TextInput::make('configuration.states.modeReadable')
                    ->disabled()
                    ->dehydrated()
                    ->label('Mode'),
                Toggle::make('configuration.states.runningStatus')
                    ->disabled()
                    ->dehydrated()
                    ->label('Running'),
                Toggle::make('configuration.states.dndState')
                    ->disabled()
                    ->dehydrated()
                    ->label('Do not Disturb'),

            ]),
            Section::make('Errors')->columns(2)->schema([

                Toggle::make('configuration.states.warningBreakdown')
                    ->disabled()
                    ->dehydrated()
                    ->label('Breakdown Error'),
                Toggle::make('configuration.states.warningWaterMissing')
                    ->disabled()
                    ->dehydrated()
                    ->label('Water Missing Error'),
                Toggle::make('configuration.states.warningFilter')
                    ->disabled()
                    ->dehydrated()
                    ->label('Filter error'),
            ])


        ];
    }
}
