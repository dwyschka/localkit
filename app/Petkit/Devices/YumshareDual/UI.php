<?php

namespace App\Petkit\Devices\YumshareDual;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use App\Filament\StateCasts\IdentityStateCast;
use App\Helpers\Time;
use App\Management\Go2RTC;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use PhpMqtt\Client\Facades\MQTT;
use Filament\Forms;
use Filament\Forms\Form;

class UI
{

    public function formFields(): array
    {
        return [
            Section::make('Stats')->schema([
                TextInput::make('configuration.states.ipAddress')
                    ->label('IP Address')
                    ->readOnly()
                    ->disabled(true),
            ]),
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
            Section::make('Feeding')->columns(2)->schema([
                TextInput::make('configuration.settings.amount1')
                    ->label('Feed Amount Hopper 1')
                    ->helperText('Default amount for manual feeding')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('configuration.settings.amount2')
                    ->label('Feed Amount Hopper 2')
                    ->helperText('Default amount for manual feeding')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('configuration.settings.factor1')
                    ->label('Hopper 1 Calibration Factor')
                    ->helperText('Calibration factor for the first hopper')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('configuration.settings.factor2')
                    ->label('Hopper 2 Calibration Factor')
                    ->helperText('Calibration factor for the second hopper')
                    ->numeric()
                    ->minValue(1),
            ]),
            Section::make('Media')->schema([
                View::make('camera_stream')->viewData(fn($record): array => [
                    'streams' => app(Go2RTC::class)->streamUrls($record)
                ])
                    ->hidden(fn($record) => is_null($record->configuration()->ipAddress))
                    ->columnSpan('full'),

                Placeholder::make('Snapshot')
                    ->content(function ($record) {
                        $image = $record->configuration()->lastSnapshot;
                        if (is_null($image)) {
                            return '';
                        }
                        $blob = Storage::disk('snapshots')->get($image);
                        if (is_null($blob)) {
                            return '';
                        }
                        $base64Blob = base64_encode($blob);
                        return new HtmlString(sprintf('<img src="data:image/jpeg;base64,%s" />', $base64Blob));
                    })
                    ->hidden(fn($record) => is_null($record->configuration()->lastSnapshot))
            ])->collapsible()
                ->hidden(fn($record) => is_null($record->configuration()->ipAddress) || !$record->mqtt_connected),

            Section::make('Camera Settings')->columns(2)->schema([
                Toggle::make('configuration.settings.camera')
                    ->helperText('Turning off the camera will not affect food dispensing, bit it will disable live streaming, playback, remaining food detection in the bowl, and other video releated functions')
                    ->label('Camera Switch'),

                Toggle::make('configuration.settings.microphone')
                    ->helperText('Enable/Disable sound collection')
                    ->label('Microphone'),

                Toggle::make('configuration.settings.night')
                    ->helperText('Enable infrared night vision in dark environment')
                    ->label('Night Vision'),

                Toggle::make('configuration.settings.timeDisplay')
                    ->label('Timestamp Display'),

                Toggle::make('configuration.settings.smartFrame')
                    ->label('Pet Tracking')
                    ->helperText('Highlight the pet when it is detected'),

                Toggle::make('configuration.settings.feedPicture')
                    ->label('Feeding Photo')
                    ->helperText('Save a photo to the cloud when food is dispensed'),

                Fieldset::make('Detection')->schema([
                    Toggle::make('configuration.settings.petDetection')
                        ->helperText('For events of pet visiting the feeder')
                        ->label('Pet Visit Detection'),
                    Select::make('configuration.settings.petSensitivity')
                        ->label('Pet Visit Sensitivity')
                        ->helperText('Sensitivity events of pet visiting the feeder')
                        ->options([
                            0 => 0,
                            1 => 1,
                            2 => 2,
                            3 => 3,
                            4 => 4,
                        ]),

                    Toggle::make('configuration.settings.eatDetection')
                        ->helperText('For events of pet eating before the camera')
                        ->label('Pet Eat Detection'),
                    Select::make('configuration.settings.eatSensitivity')
                        ->label('Pet Eat Sensitivity')
                        ->helperText('Sensitivity events of pet eating before the camera')
                        ->options([
                            0 => 0,
                            1 => 1,
                            2 => 2,
                            3 => 3,
                            4 => 4
                        ]),

                    Toggle::make('configuration.settings.moveDetection')
                        ->helperText('For events of pet moving before the camera')
                        ->label('Pet Move Detection'),
                    Select::make('configuration.settings.moveSensitivity')
                        ->helperText('Sensitivity events of pet moving before the camera')
                        ->label('Pet Move Sensitivity')->options([
                            0 => 0,
                            1 => 1,
                            2 => 2,
                            3 => 3,
                            4 => 4
                        ]),

                    TextInput::make('configuration.settings.detectInterval')
                        ->label('Detection Interval')
                        ->helperText('Minimum time between detection notifications (seconds)')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('sec'),
                ])->columnSpanFull(),
            ]),

            Section::make('Feeding Plan')
                ->schema([
                    Toggle::make('configuration.settings.sche_enable')
                        ->helperText('Enable the feeding schedule below')
                        ->label('Feeding Schedule Enabled'),

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
                                ->stateCast(new IdentityStateCast())
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
                                    Hidden::make('id')
                                        ->label('id')
                                        ->required(),

                                    TextInput::make('a')
                                        ->label('Amount')
                                        ->numeric()
                                        ->required()
                                        ->integer()
                                        ->dehydrateStateUsing(fn($state) => (int)$state)
                                        ->suffix('amount'),

                                    Hidden::make('t')
                                        ->label('Time (seconds)')
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

                                    $data = collect($state)->map(fn($s) => [
                                        'a' => $s['a'],
                                        'id' => $s['id'],
                                        't' => $s['t'] + 1,
                                    ])->toArray();

                                    return $data;
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
                ])->collapsible(),

            Section::make('Voice Settings')->columns(2)->schema([
                Select::make('configuration.settings.volume')->columnSpanFull()->label('Volume')->options([
                    1 => 1,
                    2 => 2,
                    3 => 3,
                    4 => 4,
                    5 => 5,
                    6 => 6,
                    7 => 7,
                    8 => 8,
                    9 => 9
                ]),
                Toggle::make('configuration.settings.systemSoundEnable')->label('Voice Prompt'),
                Toggle::make('configuration.settings.soundEnable')->label('Voice for Food Dispensing'),
                Toggle::make('configuration.settings.feedSound')->label('Feed Completion Sound'),


                Fieldset::make('Do not Disturb')->columns(1)->schema([
                    Toggle::make('configuration.settings.toneMode')->label('Do not disturb'),
                    Repeater::make('configuration.settings.toneMultiRange')
                        ->columns(2)
                        ->label('Undisturbed Period')
                        ->reorderableWithButtons()
                        ->schema([
                            TimePicker::make('0')
                                ->label('From')
                                ->formatStateUsing(function ($state) {
                                    return Time::toTimeFromMinutes((int)$state);
                                })
                                ->dehydrateStateUsing(function ($state) {
                                    return Time::toMinutes($state);
                                })
                                ->seconds(false),

                            TimePicker::make('1')
                                ->label('Till')
                                ->formatStateUsing(function ($state) {
                                    return Time::toTimeFromMinutes((int)$state);
                                })
                                ->dehydrateStateUsing(function ($state) {
                                    return Time::toMinutes($state);
                                })
                                ->seconds(false)
                        ])
                ])->columnSpanFull(),
            ]),
            Section::make('Settings')->columns(2)->schema([
                Toggle::make('configuration.settings.foodWarn')
                    ->helperText('Activate the sound alarm when the food container runs empty. To end the alarm press the dispense button')
                    ->label('Refill Alarm'),

                Section::make('Alarm Period')->schema([
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
                    ->columns(2)
                    ->columnSpanFull(),
                Toggle::make('configuration.settings.manualLock')
                    ->helperText('Activate Child Lock to disable the control panel')
                    ->label('Child Lock'),

                Toggle::make('configuration.settings.lightMode')
                    ->helperText('Indicator light work within the following period')
                    ->label('Indicator Light'),

                Repeater::make('configuration.settings.lightMultiRange')
                    ->columns(2)
                    ->columnSpanFull()
                    ->label('Screen Period')
                    ->schema(
                        [
                            TimePicker::make('0')
                                ->label('From')
                                ->seconds(false)
                                ->required()
                                ->formatStateUsing(
                                    fn (?string $state) => Time::toTimeFromMinutes((int) $state)
                                )
                                ->dehydrateStateUsing(
                                    fn ($state) => Time::toMinutes($state)
                                ),

                            TimePicker::make('1')
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

            ]),
            Section::make('AI LAB')->columns(2)->schema([
                Toggle::make('configuration.settings.vomitDetection')
                    ->label('Vomit Detection')
                    ->helperText('Uses AI behavior recognition to detect signs of vomiting in cats and sends an instant notification'),

                Select::make('configuration.settings.surplusControl')
                    ->label('Surplus Food Control')
                    ->helperText('Pause feeding while surplus food remains in the bowl')
                    ->options([
                        0 => 'off',
                        1 => 'on',
                    ]),

                TextInput::make('configuration.settings.surplusStandard')
                    ->label('Surplus Food Standard')
                    ->helperText('Minimum amount of surplus food in the bowl before feeding pauses')
                    ->numeric()
                    ->minValue(0),
            ]),

            Section::make('Unknown')->columns(3)->schema([
                ViewField::make('UnknownWarning')
                    ->columnSpanFull()
                    ->view('filament.forms.warning')
                    ->viewData(['message' => 'Its Unknown, because the changes are not verified']),
                Toggle::make('configuration.settings.shareOpen')->label('Share Open'),
                Toggle::make('configuration.settings.multiConfig')->label('Multi Config'),

                Toggle::make('configuration.settings.upload')
                    ->label('Upload')
                    ->helperText('Enable cloud upload (unverified)'),

            ]),



        ];
    }

}
