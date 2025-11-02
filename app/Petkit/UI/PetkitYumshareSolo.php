<?php

namespace App\Petkit\UI;

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
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use PhpMqtt\Client\Facades\MQTT;
use Filament\Forms;
use Filament\Forms\Form;

class PetkitYumshareSolo
{
    use HiddenFields;

    public function formFields(): array
    {
        return [
            Section::make('Stream')->schema([
                Forms\Components\View::make('camera_stream')->viewData([
                    'stream' => 'http://10.10.46.30:1984/stream.html?src=camera'
                ])
                    ->hidden(fn($record) => is_null($record->definition()->configurationDefinition()->getIpAddress()))
                    ->columnSpan('full'),
                Forms\Components\Placeholder::make('Snapshot')
                    ->content(fn($record): HtmlString => new HtmlString(sprintf('<img src="%s" />', $record->definition()->configurationDefinition()->getLastSnapshot())))
                    ->hidden(fn($record) => is_null($record->definition()->configurationDefinition()->getLastSnapshot()))
            ])->collapsible(),

            Section::make('Schedule Configuration')
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
                                        ->label('id')
                                        ->numeric()
                                        ->hidden() // Hidden field to store the actual seconds value
                                        ->required(),
                                    TextInput::make('a')
                                        ->label('Amount')
                                        ->numeric()
                                        ->required()
                                        ->integer()
                                        ->dehydrateStateUsing(fn($state) => (int)$state)
                                        ->suffix('amount'),
                                    TextInput::make('t')
                                        ->label('Time (seconds)')
                                        ->numeric()
                                        ->hidden() // Hidden field to store the actual seconds value
                                        ->required(),
                                ])
                                ->columns(2)
                                ->addActionLabel('Add Schedule Item')
                                ->minItems(1)
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
                                        'time_display' => $s['time_display']
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
                        ->minItems(1)
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
                ])->collapsible(),
            Forms\Components\Section::make('Camera/Microphone')->columns(3)->schema([
                Forms\Components\Toggle::make('configuration.settings.camera')->label('Enable Camera'),
                Forms\Components\Toggle::make('configuration.settings.microphone')->label('Enable Microphone'),
                Forms\Components\Toggle::make('configuration.settings.timeDisplay')->label('Display Time in Video'),
                Forms\Components\Toggle::make('configuration.settings.night')->label('Night'),
                Forms\Components\Toggle::make('configuration.settings.smartFrame')->label('Display Border')->helperText('Display Border if Pet is detected'),
            ]),
            Forms\Components\Section::make('Audio')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.systemSoundEnable')->label('Device Audio enable?'),
                Forms\Components\Toggle::make('configuration.settings.soundEnable')->label('Voice announcement after feeding?'),
                Forms\Components\Select::make('configuration.settings.volume')->label('Volume')->options([
                    1 => 1,
                    2 => 2,
                    3 => 3,
                    4 => 4,
                    5 => 5,
                    6 => 6,
                    7 => 7,
                    8 => 8,
                    9 => 9
                ])

            ]),
            Forms\Components\Section::make('Detection')->columns(2)->schema([
                Section::make('Move')->columns(2)->schema([
                    Forms\Components\Toggle::make('configuration.settings.moveDetection')->label('Move Detection'),
                    Forms\Components\Select::make('configuration.settings.moveSensitivity')->label('Move Sensitivity')->options([
                        0 => 0,
                        1 => 1,
                        2 => 2,
                        3 => 3,
                        4 => 4
                    ])
                ]),
                Section::make('Pet')->columns(2)->schema([
                    Forms\Components\Toggle::make('configuration.settings.petDetection')->label('Move Detection'),
                    Forms\Components\Select::make('configuration.settings.petSensitivity')->label('Pet Sensitivity')->options([
                        0 => 0,
                        1 => 1,
                        2 => 2,
                        3 => 3,
                        4 => 4,
                    ])
                ]),
                Section::make('Eat')->columns(2)->schema([

                    Forms\Components\Toggle::make('configuration.settings.eatDetection')->label('Eat Detection'),
                    Forms\Components\Select::make('configuration.settings.eatSensitivity')->label('Eat Sensitivity')->options([
                        0 => 0,
                        1 => 1,
                        2 => 2,
                        3 => 3,
                        4 => 4
                    ])
                ])
            ]),
            Forms\Components\Section::make('AI LAB')->schema([
                Forms\Components\Select::make('configuration.settings.surplusControl')->options([
                    0 => 'off',

                ])
            ]),

            Forms\Components\Section::make('Feed')->schema([
                Forms\Components\TextInput::make('configuration.settings.factor')->numeric(),
            ]),
            Forms\Components\Section::make('Options')->columns(3)->schema([
                Forms\Components\Toggle::make('configuration.settings.feedSound')->label('Feeding chime'),
                Forms\Components\Toggle::make('configuration.settings.manualLock')->label('Child Lock'),
                Forms\Components\Toggle::make('configuration.settings.foodWarn')->label('Refill alarm'),
                Forms\Components\Toggle::make('configuration.settings.autoUpgrade')->label('Auto-Upgrade')->disabled(true),

            ]),
            Forms\Components\Section::make('Time')->columns(3)->schema([

                Forms\Components\Section::make('foodWarnRange')->schema([
                    TimePicker::make('configuration.settings.foodWarnRange.0')
                        ->label('From')
                        ->seconds(false)
                        ->required()
                        ->formatStateUsing(fn(?string $state) => Time::toTimeFromMinutes((int)$state ?? 0))
                        ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                    TimePicker::make('configuration.settings.foodWarnRange.1')
                        ->label('Till')
                        ->required()
                        ->seconds(false)
                        ->after('time_from')
                        ->formatStateUsing(fn(?string $state) => Time::toTimeFromMinutes((int)$state ?? 0))
                        ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                ])
                    ->columns(2)
                    ->columnSpanFull(),

                Forms\Components\Section::make('lightMultiRange')->schema([
                    TimePicker::make('configuration.settings.lightRange.0')
                        ->label('From')
                        ->seconds(false)
                        ->required()
                        ->formatStateUsing(fn(?string $state) => Time::toTimeFromMinutes((int)$state ?? 0))
                        ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                    TimePicker::make('configuration.settings.lightRange.1')
                        ->label('Till')
                        ->required()
                        ->seconds(false)
                        ->after('time_from')
                        ->formatStateUsing(fn(?string $state) => Time::toTimeFromMinutes((int)$state ?? 0))
                        ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                ])
                    ->columns(2)
                    ->columnSpanFull(),

                Forms\Components\Section::make('Do not Disturb')->schema([
                    TimePicker::make('configuration.settings.lightRange.0')
                        ->label('From')
                        ->seconds(false)
                        ->required()
                        ->formatStateUsing(fn(?string $state) => Time::toTimeFromMinutes((int)$state ?? 0))
                        ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                    TimePicker::make('configuration.settings.lightRange.1')
                        ->label('Till')
                        ->required()
                        ->seconds(false)
                        ->after('time_from')
                        ->formatStateUsing(fn(?string $state) => Time::toTimeFromMinutes((int)$state ?? 0))
                        ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                ])
                    ->columns(2)
                    ->columnSpanFull(),

          ]),
            Forms\Components\Section::make('Unknown')->columns(3)->schema([
                Forms\Components\Toggle::make('configuration.settings.shareOpen')->label('Share Open'),
                Forms\Components\Toggle::make('configuration.settings.multiConfig')->label('Multi Config'),
            ]),

            Forms\Components\Hidden::make('configuration.capacity.0.name')->default('fullVideo'),
            Forms\Components\Hidden::make('configuration.capacity.1.name')->default('eventImage'),
            Forms\Components\Hidden::make('configuration.capacity.2.name')->default('highLight'),
            Forms\Components\Hidden::make('configuration.capacity.3.name')->default('dynamicVideo'),


        ];
    }

}
