<?php

namespace App\Petkit\Devices\FreshElement3;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Select;
use App\Helpers\Time;
use App\Jobs\ServiceEnd;
use App\Jobs\ServiceStart;
use App\Models\Device;
use App\MQTT\GenericReply;
use App\MQTT\OtaMessage;
use App\MQTT\UserGet;
use App\Petkit\DeviceActions;
use App\Petkit\DeviceDefinition;
use App\Petkit\DeviceStates;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Filament\Forms;
use Filament\Forms\Form;

class UI
{

    public function formFields(): array
    {
        return [
            Section::make('Consumables')->columns(2)->schema([
                TextInput::make('configuration.consumables.desiccantDurability')->numeric(),
                TextInput::make('configuration.consumables.desiccantNextChange')->label('Next Reset in Days (Desiccant)')->formatStateUsing(function ($state) {
                    if ($state <= 0) {
                        return 'Not set';
                    }
                    $date = Carbon::parse($state);
                    $now = Carbon::now();

                    return round($now->diffInDays($date));

                })->readOnly()->disabled(true),
            ]),
            Section::make('Feeding')->schema([
                TextInput::make('configuration.settings.amount')
                    ->label('Feeding Amount')
                    ->helperText('Default amount for manual feeding')
                    ->numeric()
            ]),
            Section::make('Settings')->columns(2)->schema([
                Toggle::make('configuration.settings.manualLock')
                    ->helperText('Activate Child Lock to disable the control panel')
                    ->columnSpan('half')
                    ->label('Child Lock'),

                Toggle::make('configuration.settings.lightMode')
                    ->helperText('Indicator light work within the following period')
                    ->label('Indicator Light'),

                Section::make('Light Period')->schema([
                    TimePicker::make('configuration.settings.lightRange.from')
                        ->formatStateUsing(function ($state) {
                            return Time::toTimeFromMinutes((int)$state);
                        })
                        ->dehydrateStateUsing(function ($state) {
                            return Time::toMinutes($state);
                        })
                        ->seconds(false),

                    TimePicker::make('configuration.settings.lightRange.till')
                        ->formatStateUsing(function ($state) {
                            return Time::toTimeFromMinutes((int)$state);
                        })
                        ->dehydrateStateUsing(function ($state) {
                            return Time::toMinutes($state);
                        })
                        ->seconds(false)
                ])
                    ->columns(2)
                    ->columnSpanFull(),

                Toggle::make('configuration.settings.soundEnable')
                    ->helperText('Play the voice when the food is dispensing')
                    ->label('Voice for Food Dispensing'),

                Toggle::make('configuration.settings.systemSoundEnable')
                    ->helperText('Turn on the voice prompt of the device')
                    ->label('Voice Prompt'),

                TextInput::make('configuration.settings.volume')
                    ->label('Volume')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(9),

                Toggle::make('configuration.settings.disturbMode')
                    ->helperText('Mute sounds and lights within the following period')
                    ->label('Do Not Disturb'),

                Section::make('Do Not Disturb Period')->schema([
                    TimePicker::make('configuration.settings.disturbRange.from')
                        ->formatStateUsing(function ($state) {
                            return Time::toTimeFromMinutes((int)$state);
                        })
                        ->dehydrateStateUsing(function ($state) {
                            return Time::toMinutes($state);
                        })
                        ->seconds(false),

                    TimePicker::make('configuration.settings.disturbRange.till')
                        ->formatStateUsing(function ($state) {
                            return Time::toTimeFromMinutes((int)$state);
                        })
                        ->dehydrateStateUsing(function ($state) {
                            return Time::toMinutes($state);
                        })
                        ->seconds(false)
                ])
                    ->columns(2)
                    ->columnSpanFull(),

                Toggle::make('configuration.settings.surplusControl')
                    ->helperText('Pause dispensing while enough food is left in the bowl')
                    ->label('Surplus Control'),

                TextInput::make('configuration.settings.surplus')
                    ->label('Surplus Amount')
                    ->numeric()
                    ->suffix('g'),


            ]),
            Section::make('Feeding Plan')
                ->schema([
                    Repeater::make('configuration.schedule')

                        ->schema([
                            CheckboxList::make('re')
                                ->label('Days of Week')
                                ->options([
                                    '1' => 'Sunday',
                                    '2' => 'Monday',
                                    '3' => 'Tuesday',
                                    '4' => 'Wednesday',
                                    '5' => 'Thursday',
                                    '6' => 'Friday',
                                    '7' => 'Saturday',
                                ])
                                ->columns(4)
                                ->required()
                                ->formatStateUsing(fn(string|array $state) => is_array($state) ? $state : explode(',', $state))
                                ->dehydrateStateUsing(fn($state) => implode(',', Arr::sort(array_filter($state)))),

                            Repeater::make('it')
                                ->label('Schedule Items')
                                ->schema([
                                    TimePicker::make('time_display')
                                        ->label('Time')
                                        ->seconds(false)
                                        ->required()
                                        ->dehydrated(false)
                                        ->afterStateUpdated(function (Set $set, $state) {
                                            if ($state) {
                                                // Convert time to seconds from midnight;
                                                $seconds = Time::toSeconds($state);
                                                $set('t', $seconds);
                                                $set('id', sprintf('n_%d', $seconds));
                                            }
                                        })
                                        ->afterStateHydrated(function (Set $set, Get $get) {
                                            $seconds = $get('t');
                                            if ($seconds) {
                                                // Convert seconds back to time format
                                                $time = Time::toTimeFromSeconds($seconds);
                                                $set('time_display', $time);
                                            }
                                        }),
                                    TextInput::make('id')
                                        ->hidden(true)
                                        ->dehydratedWhenHidden(true)
                                        ->label('id')
                                        ->required(),
                                    TextInput::make('a')
                                        ->label('Amount')
                                        ->numeric()
                                        ->required()
                                        ->integer()
                                        ->dehydrateStateUsing(fn($state) => (int)$state)
                                        ->suffix('amount'),
                                    TextInput::make('t')
                                        ->hidden(true)
                                        ->label('Time (seconds)')
                                        ->numeric()
                                        ->dehydratedWhenHidden(true)
                                        ->required(),
                                ])
                                ->columns(2)
                                ->addActionLabel('Add Schedule Item')
                                ->minItems(0)
                                ->collapsible()
                                ->live(true)
                                ->dehydrateStateUsing(function (array $state) {
                                    if (!is_array($state)) return $state;

                                    // Sort by time_display treating it as time
                                    uasort($state, function ($a, $b) {
                                        $timeA = $a['time_display'] ?? '00:00';
                                        $timeB = $b['time_display'] ?? '00:00';

                                        // Convert to comparable format (HHMM as integer)
                                        $intA = (int)str_replace(':', '', $timeA);
                                        $intB = (int)str_replace(':', '', $timeB);

                                        return $intA <=> $intB;
                                    });

                                    return collect($state)->map(fn($s) => [
                                        'a' => $s['a'],
                                        'id' => $s['id'],
                                        't' => $s['t'] + 1,
                                    ])->toArray();
                                })
                                ->itemLabel(function (array $state): ?string {
                                    $time = '';
                                    if (!empty($state['t'])) {
                                        $seconds = $state['t'];
                                        $hours = floor($seconds / 3600);
                                        $minutes = floor(($seconds % 3600) / 60);
                                        $time = sprintf('%02d:%02d', $hours, $minutes);
                                    }
                                    $amount = $state['a'] ?? '';
                                    return $time ? "{$time} ({$amount}g)" : 'New Item';
                                }),
                        ])
                        ->columns(1)
                        ->addActionLabel('Add Day Schedule')
                        ->minItems(0)
                        ->collapsible()
                        ->itemLabel(function (array $state): ?string {
                            $days = [];
                            if (!empty($state['re'])) {
                                $dayNumbers = is_string($state['re']) ? explode(',', $state['re']) : $state['re'];
                                $dayNames = [
                                    '1' => 'Sun', '2' => 'Mon', '3' => 'Tue', '4' => 'Wed',
                                    '5' => 'Thu', '6' => 'Fri', '7' => 'Sat'
                                ];
                                foreach ($dayNumbers as $day) {
                                    if (isset($dayNames[$day])) {
                                        $days[] = $dayNames[$day];
                                    }
                                }
                            }
                            return !empty($days) ? implode(', ', $days) : 'New Schedule';
                        }),
                ]),
            Section::make('Unknown')->columns(2)->schema([

                ViewField::make('UnknownWarning')
                    ->columnSpanFull()
                    ->view('filament.forms.warning')
                    ->viewData(['message' => 'Its Unknown, because the changes are not verified']),


                Select::make('configuration.settings.language')->options([
                    'en_US' => 'English',
                    'zh_CN' => 'Chinese'
                ])->label('Language'),

                TextInput::make('configuration.settings.selectedSound')
                    ->columnSpan('half')
                    ->readOnly()
                    ->numeric()
                    ->label('Selected Sound'),
                TextInput::make('configuration.settings.numLimit')
                    ->columnSpan('half')
                    ->readOnly()
                    ->numeric()
                    ->label('Num Limit'),
            ]),


        ];
    }

}
