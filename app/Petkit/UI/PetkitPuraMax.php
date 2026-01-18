<?php

namespace App\Petkit\UI;

use App\Helpers\Time;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TimePicker;
use Illuminate\Support\Carbon;
use Filament\Forms;

class PetkitPuraMax
{

    public function formFields(): array
    {
        return [
            Forms\Components\Section::make('consumables')->columns(3)->schema([
                Forms\Components\TextInput::make('configuration.consumables.n50Durability')->numeric(),
                Forms\Components\TextInput::make('configuration.consumables.n50NextChange')->label('Next Reset in Days (N50)')->formatStateUsing(function ($state) {
                    if ($state <= 0) {
                        return 'Not set';
                    }
                    $date = Carbon::parse($state);
                    $now = Carbon::now();

                    return round($now->diffInDays($date));

                })->readOnly()->disabled(true),
                Forms\Components\TextInput::make('configuration.consumables.k3Battery')->numeric()->readOnly()->disabled(true),
                Forms\Components\TextInput::make('configuration.consumables.k3Liquid')->numeric()->readOnly()->disabled(true),

            ]),
            Forms\Components\Section::make('Litter')->columns(3)->schema([
                Forms\Components\TextInput::make('history_count')->readOnly()->formatStateUsing(function ($record) {
                    return $record->histories()->whereDate('created_at', now()->toDateTime())->where('type', '=', 'IN_USE')->count();
                }),
                Forms\Components\TextInput::make('configuration.litter.percent')->readOnly(),
                Forms\Components\TextInput::make('configuration.litter.weight')->readOnly(),
            ]),
            Forms\Components\Section::make('Settings')->schema([
                Forms\Components\Select::make('configuration.settings.sandType')->options([
                    '1' => 'Betonit/Mineral',
                    '2' => 'Tofu',
                    '3' => 'Sand'
                ])->label('Litter Type'),

                Forms\Components\Toggle::make('configuration.settings.downpos')
                    ->helperText('Cylinder will rotate from its initial level and wont pause during the process')
                    ->label('Uninterrupted Rotation'),

                Forms\Components\Toggle::make('configuration.settings.manualLock')
                    ->helperText('After activating the child lock, the buttons on the device are not available during standby')
                    ->label('Child Lock'),

                Forms\Components\Toggle::make('configuration.settings.lightMode')
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

                Forms\Components\Select::make('configuration.settings.language')->options([
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

                Forms\Components\Select::make('configuration.settings.unit')->options([
                    '0' => 'Kg',
                    '1' => 'Pound'
                ]),
            ]),

            Forms\Components\Section::make('Smart Settings')->schema([
                Forms\Components\Toggle::make('configuration.settings.kitten')
                    ->helperText('Disable auto and periodical Cleaning')
                    ->label('Kitten Protection'),

                Forms\Components\Toggle::make('configuration.settings.autoWork')
                    ->helperText('After pet leaves, the device will start automatic cleaning')
                    ->label('Auto Cleaning'),

                Forms\Components\Toggle::make('configuration.settings.fixedTimeClear')
                    ->helperText('The device will initiate cleaning at the time set, Currently not supported')
                    ->disabled()
                    ->label('Scheduled Cleaning'),

                Forms\Components\Select::make('configuration.settings.stillTime')
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

                Forms\Components\Toggle::make('configuration.settings.avoidRepeat')
                    ->helperText('Set based on Cleaning interval or pet weight')
                    ->label('Avoid Repeated Cleaning'),


                Forms\Components\Select::make('configuration.settings.autoIntervalMin')
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

                Forms\Components\Toggle::make('configuration.settings.underweight')
                    ->helperText('When the device detects that the pet\'s weight is less than the actual minimum weight, it will not start automatic cleaning')
                    ->label('Disable auto cleaning for light weight'),

                Forms\Components\TextInput::make('configuration.settings.lightest')
                    ->label('Minimum Weight')
                    ->helperText('The device will not start automatic cleaning when the pet\'s weight is less than the actual minimum weight'),

                Forms\Components\Toggle::make('configuration.settings.deepClean')
                    ->label('Deep Cleaning')
                    ->helperText('After being enabled, it can effecticvely remove the waste residue'),

                Forms\Components\Toggle::make('configuration.settings.bury')
                    ->helperText('Help evenly cover waste with cat litter before cleaning cycle')
                    ->label('Waste Covering'),


                    Forms\Components\Toggle::make('configuration.settings.disturbMode')
                        ->label('Do not Disturb'),

                    Forms\Components\Hidden::make('configuration.settings.disturbMultiRange.name')->default('disturbMultiRange'),
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


            Forms\Components\Section::make('K3')->schema([
                Forms\Components\Fieldset::make('Device')->schema([
                    Forms\Components\TextInput::make('configuration.k3Device.id')->label('K3 ID'),
                    Forms\Components\TextInput::make('configuration.k3Device.mac')->label('K3 MAC'),
                    Forms\Components\TextInput::make('configuration.k3Device.sn')->label('K3 Serial Number'),
                    Forms\Components\TextInput::make('configuration.k3Device.secret')->label('K3 Secret'),
                ]),
                Forms\Components\Fieldset::make('Settings')->schema([
                    Forms\Components\ViewField::make('k3Warning')
                        ->columnSpanFull()
                        ->view('filament.forms.warning')
                        ->viewData(['message' => 'Its possible to manipulate the values, but its not verified']),

                    Forms\Components\KeyValue::make('configuration.settings.k3Config.standard')->label('Standard'),
                    Forms\Components\TextInput::make('configuration.settings.k3Config.lightness')->label('Lightness'),
                    Forms\Components\TextInput::make('configuration.settings.k3Config.lowVoltage')->label('Low Voltage'),
                    Forms\Components\TextInput::make('configuration.settings.k3Config.refreshTotalTime')->label('Refresh Total Time'),
                    Forms\Components\TextInput::make('configuration.settings.k3Config.singleRefreshTime')->label('Single Refresh Time'),
                    Forms\Components\TextInput::make('configuration.settings.k3Config.singleLightTime')->label('Single Light Time'),

                ])



            ]),

            Forms\Components\Section::make('Unknown')->schema([
                Forms\Components\ViewField::make('warning')
                    ->columnSpanFull()
                    ->view('filament.forms.warning')
                    ->viewData(['message' => 'Its Unknown, because the changes are not verified']),

                Forms\Components\Select::make('configuration.settings.stopTime')
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

                Forms\Components\TextInput::make('configuration.settings.petInTipLimit')
                    ->helperText('The meaning is currently unknown'),

                Forms\Components\Toggle::make('configuration.settings.shareOpen')
                    ->helperText('The meaning is currently unknown')
                    ->label('Share Open'),

                Forms\Components\Toggle::make('configuration.settings.removeSand')
                    ->helperText('The meaning is currently unknown')
                    ->label('Remove Sand'),

                Forms\Components\Toggle::make('configuration.settings.clickOkEnable')
                    ->helperText('The meaning is currently unknown')
                    ->label('Click OK Enable'),

                Forms\Components\KeyValue::make('configuration.settings.sandFullWeight')
                    ->helperText('The meaning is currently unknown')
                    ->label('SandFullWeight'),

                Forms\Components\KeyValue::make('configuration.settings.sandSetUseConfig')
                    ->disabled(true)
                    ->helperText('The meaning is currently unknown')

            ]),


        ];
    }
}
