<?php

namespace App\Petkit\Devices\PuraMax;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\KeyValue;
use App\Helpers\Time;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TimePicker;
use Illuminate\Support\Carbon;
use Filament\Forms;

class UI
{

    public function formFields(): array
    {
        return [
            Section::make('consumables')->columns(3)->schema([
                TextInput::make('configuration.consumables.n50Durability')->numeric(),
                TextInput::make('configuration.consumables.n50NextChange')->label('Next Reset in Days (N50)')->formatStateUsing(function ($state) {
                    if ($state <= 0) {
                        return 'Not set';
                    }
                    $date = Carbon::parse($state);
                    $now = Carbon::now();

                    return round($now->diffInDays($date));

                })->readOnly()->disabled(true)
            ]),
            Section::make('Litter')->columns(3)->schema([
                TextInput::make('history_count')->readOnly()->formatStateUsing(function ($record) {
                    return $record->histories()->whereDate('created_at', now()->toDateTime())->where('type', '=', 'IN_USE')->count();
                }),
                TextInput::make('configuration.litter.percent')->readOnly(),
                TextInput::make('configuration.litter.weight')->readOnly(),
            ]),
            Section::make('Settings')->schema([
                Select::make('configuration.settings.sandType')->options([
                    '1' => 'Betonit/Mineral',
                    '2' => 'Tofu',
                    '3' => 'Sand'
                ])->label('Litter Type'),

                Toggle::make('configuration.settings.downpos')
                    ->helperText('Cylinder will rotate from its initial level and wont pause during the process')
                    ->label('Uninterrupted Rotation'),

                Toggle::make('configuration.settings.manualLock')
                    ->helperText('After activating the child lock, the buttons on the device are not available during standby')
                    ->label('Child Lock'),

                Toggle::make('configuration.settings.lightMode')
                    ->helperText('After turning it off, the display will automatically turn off when the device is not in operation')
                    ->label('Set Screen Display'),

                Repeater::make('configuration.settings.lightMultiRange.ranges')
                    ->columns(2)
                    ->label('Screen Period')
                    ->schema(
                        [
                            TimePicker::make('from')
                                ->label('From')
                                ->seconds(false)
                                ->required()
                                ->formatStateUsing(
                                    fn (?string $state) => Time::toTimeFromMinutes((int) $state)
                                )
                                ->dehydrateStateUsing(
                                    fn ($state) => Time::toMinutes($state)
                                ),

                            TimePicker::make('till')
                                ->label('Till')
                                ->seconds(false)
                                ->required()
                                ->formatStateUsing(
                                    fn (?string $state) => Time::toTimeFromMinutes((int) $state)
                                )
                                ->dehydrateStateUsing(
                                    fn ($state) => Time::toMinutes($state)
                                ),
                        ]
                    ),

                Select::make('configuration.settings.language')->options([
                    'en_US' => 'English',
                    'de_DE' => 'German',
                    'es_ES' => 'Spanish',
                    'zh_CN' => 'Chinese',
                    'ja_JP' => 'Japanese',
                    'it_IT' => 'Italiano',
                    'pt_PT' => 'Portuguese',
                    'tr_TR' => 'Turkish',
                    'ru_RU' => 'Russian',
                    'fr_FR' => 'French',
                ])->label('Language'),

                Select::make('configuration.settings.unit')->options([
                    '0' => 'Kg',
                    '1' => 'Pound'
                ]),
            ]),

            Section::make('Smart Settings')->schema([
                Toggle::make('configuration.settings.kitten')
                    ->helperText('Disable auto and periodical Cleaning')
                    ->label('Kitten Protection'),

                Toggle::make('configuration.settings.sandSaving')
                    ->helperText('Turn it on when clumps are relatively large')
                    ->label('Litter-saving Mode'),

                Toggle::make('configuration.settings.autoWork')
                    ->helperText('After pet leaves, the device will start automatic cleaning')
                    ->label('Auto Cleaning'),

                Toggle::make('configuration.settings.fixedTimeClear')
                    ->helperText('The device will initiate cleaning at the time set, Currently not supported')
                    ->disabled()
                    ->label('Scheduled Cleaning'),

                Select::make('configuration.settings.stillTime')
                    ->label('Delayed Cleaning')
                    ->helperText('Set time interval before device starts cleaning')
                    ->options([
                        '0' => 'Off',
                        '30' => '30 seconds',
                        '60' => '1 Minute',
                        '300' => '5 Minutes',
                        '600' => '10 Minutes',
                        '900' => '15 Minutes',
                        '1800' => '30 Minutes',
                        '3600' => '1 Hour',
                    ]),

                Toggle::make('configuration.settings.avoidRepeat')
                    ->helperText('Set based on Cleaning interval or pet weight')
                    ->label('Avoid Repeated Cleaning'),


                Select::make('configuration.settings.autoIntervalMin')
                    ->label('Time interval of each cleaning')
                    ->helperText('During the set time, the device will not clean repeatedly after the pet used it for multiple times')
                    ->options([
                        '0' => 'Off',
                        '30' => '30 seconds',
                        '60' => '1 Minute',
                        '300' => '5 Minutes',
                        '600' => '10 Minutes',
                        '900' => '15 Minutes',
                        '1800' => '30 Minutes',
                        '3600' => '1 Hour',
                    ]),

                Toggle::make('configuration.settings.underweight')
                    ->helperText('When the device detects that the pet\'s weight is less than the actual minimum weight, it will not start automatic cleaning')
                    ->label('Disable auto cleaning for light weight'),

                TextInput::make('configuration.settings.lightest')
                    ->label('Minimum Weight')
                    ->helperText('The device will not start automatic cleaning when the pet\'s weight is less than the actual minimum weight'),

                Toggle::make('configuration.settings.deepClean')
                    ->label('Deep Cleaning')
                    ->helperText('After being enabled, it can effecticvely remove the waste residue'),

                Toggle::make('configuration.settings.bury')
                    ->helperText('Help evenly cover waste with cat litter before cleaning cycle')
                    ->label('Waste Covering'),


                    Toggle::make('configuration.settings.disturbMode')
                        ->label('Do not Disturb'),

                    Hidden::make('configuration.settings.disturbMultiRange.name')->default('disturbMultiRange'),
                    Repeater::make('configuration.settings.disturbMultiRange.ranges')
                        ->columns(2)
                        ->label('Do not Disturb Period')
                        ->reorderableWithButtons()
                        ->schema([
                            TimePicker::make('from')
                                ->formatStateUsing(function ($state) {
                                    return Time::toTimeFromMinutes((int)$state);
                                })
                                ->dehydrateStateUsing(function ($state) {
                                    return Time::toMinutes($state);
                                })
                                ->seconds(false),

                            TimePicker::make('till')
                                ->formatStateUsing(function ($state) {
                                    return Time::toTimeFromMinutes((int)$state);
                                })
                                ->dehydrateStateUsing(function ($state) {
                                    return Time::toMinutes($state);
                                })
                                ->seconds(false)
                        ])
                        ->dehydrateStateUsing(function ($state) {
                            return $state;
                        })
                ]),

            Section::make('Unknown')->schema([
                ViewField::make('warning')
                    ->columnSpanFull()
                    ->view('filament.forms.warning')
                    ->viewData(['message' => 'Its Unknown, because the changes are not verified']),

                Select::make('configuration.settings.stopTime')
                    ->helperText('The meaning is currently unknown')
                    ->options([
                    '0' => 'Off',
                    '30' => '30 seconds',
                    '60' => '1 Minute',
                    '300' => '5 Minutes',
                    '600' => '10 Minutes',
                    '900' => '15 Minutes',
                    '1800' => '30 Minutes',
                    '3600' => '1 Hour',
                ]),

                TextInput::make('configuration.settings.petInTipLimit')
                    ->helperText('The meaning is currently unknown'),

                Toggle::make('configuration.settings.shareOpen')
                    ->helperText('The meaning is currently unknown')
                    ->label('Share Open'),

                Toggle::make('configuration.settings.removeSand')
                    ->helperText('The meaning is currently unknown')
                    ->label('Remove Sand'),

                Toggle::make('configuration.settings.clickOkEnable')
                    ->helperText('The meaning is currently unknown')
                    ->label('Click OK Enable'),

                KeyValue::make('configuration.settings.sandFullWeight')
                    ->helperText('The meaning is currently unknown')
                    ->label('SandFullWeight'),

                KeyValue::make('configuration.settings.sandSetUseConfig')
                    ->disabled(true)
                    ->helperText('The meaning is currently unknown')

            ]),


        ];
    }
}
