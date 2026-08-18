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
                    ->disabled(),
                TextInput::make('configuration.consumables.filterTimeLeftDays')
                    ->label('Filter Time Left (days)')
                    ->dehydrated()
                    ->disabled(),
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
                    ->label('Do not Disturb (active now)'),
                Toggle::make('configuration.settings.doNotDisturbSwitch')
                    ->disabled()
                    ->dehydrated()
                    ->label('Do not Disturb (enabled)'),
                TextInput::make('configuration.settings.doNotDisturbTimeOnReadable')
                    ->disabled()
                    ->dehydrated()
                    ->label('Do not Disturb Start'),
                TextInput::make('configuration.settings.doNotDisturbTimeOffReadable')
                    ->disabled()
                    ->dehydrated()
                    ->label('Do not Disturb End'),

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
            ]),
            Section::make('LED & Smart Mode')->columns(2)->schema([
                Toggle::make('configuration.settings.ledSwitch')
                    ->disabled()
                    ->dehydrated()
                    ->label('LED'),
                TextInput::make('configuration.settings.ledBrightness')
                    ->disabled()
                    ->dehydrated()
                    ->label('LED Brightness'),
                TextInput::make('configuration.settings.ledLightTimeOnReadable')
                    ->disabled()
                    ->dehydrated()
                    ->label('LED On At'),
                TextInput::make('configuration.settings.ledLightTimeOffReadable')
                    ->disabled()
                    ->dehydrated()
                    ->label('LED Off At'),
                TextInput::make('configuration.settings.smartTimeOn')
                    ->disabled()
                    ->dehydrated()
                    ->label('Smart Mode Time On (min)'),
                TextInput::make('configuration.settings.smartTimeOff')
                    ->disabled()
                    ->dehydrated()
                    ->label('Smart Mode Time Off (min)'),
            ]),
            Section::make('Statistics')->columns(2)->schema([
                TextInput::make('configuration.stats.pumpRuntimeReadable')
                    ->disabled()
                    ->dehydrated()
                    ->label('Pump Runtime'),
                TextInput::make('configuration.stats.pumpRuntimeTodayReadable')
                    ->disabled()
                    ->dehydrated()
                    ->label('Pump Runtime Today'),
                TextInput::make('configuration.stats.purifiedWaterLiters')
                    ->disabled()
                    ->dehydrated()
                    ->label('Purified Water (L)'),
                TextInput::make('configuration.stats.purifiedWaterTodayLiters')
                    ->disabled()
                    ->dehydrated()
                    ->label('Purified Water Today (L)'),
                TextInput::make('configuration.stats.energyConsumedKwh')
                    ->disabled()
                    ->dehydrated()
                    ->label('Energy Consumed (kWh)'),
            ]),
        ];
    }
}
