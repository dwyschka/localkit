<?php

namespace App\Petkit\UI;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use App\Helpers\Time;
use App\Management\Go2RTC;
use Illuminate\Support\Carbon;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Filament\Forms;

class PetkitEversweetUltra
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
                TextInput::make('configuration.consumables.cubeDurability')
                    ->label('Cube Durability')
                    ->suffix('days')
                    ->numeric(),
                TextInput::make('configuration.consumables.cubeNextChange')
                    ->label('Next Cube Change in Days')
                    ->formatStateUsing(function ($state) {
                        if ($state <= 0) {
                            return 'Not set';
                        }
                        $date = Carbon::parse($state);
                        $now = Carbon::now();

                        return round($now->diffInDays($date));
                    })
                    ->readOnly()
                    ->disabled(true),
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
                    ->helperText('Turning off the camera will disable live streaming, snapshots and all detection features')
                    ->label('Camera'),

                Toggle::make('configuration.settings.microphone')
                    ->helperText('Enable/Disable sound collection')
                    ->label('Microphone'),

                Toggle::make('configuration.settings.night')
                    ->helperText('Enable infrared night vision in dark environment')
                    ->label('Night Vision'),

                Toggle::make('configuration.settings.cameraLight')
                    ->helperText('When enabled, the camera indicator light is solid green while active')
                    ->label('Camera Indicator Light'),

                Toggle::make('configuration.settings.microLight')
                    ->helperText('When enabled, the indicator light is on while the microphone is active')
                    ->label('Microphone Indicator Light'),

                Toggle::make('configuration.settings.smartFrame')
                    ->label('Pet Tracking')
                    ->helperText('Requires Pet Detection to be enabled'),

                Fieldset::make('Detection')->schema([
                    Toggle::make('configuration.settings.petDetection')
                        ->helperText('Detect when a pet appears in the frame')
                        ->label('Pet Appearance Detection'),

                    Toggle::make('configuration.settings.drinkDetection')
                        ->helperText('Detect when a pet is drinking')
                        ->label('Drinking Detection'),
                ])
            ]),

            Section::make('Water Management')->columns(2)->schema([
                Toggle::make('configuration.settings.addWaterSwitch')
                    ->label('Refill')
                    ->helperText('Manually trigger a refill cycle'),

                TextInput::make('configuration.settings.addWaterMode')
                    ->label('Add Water Mode')
                    ->numeric()
                    ->minValue(0),

                Toggle::make('configuration.settings.autoWaterChange')
                    ->label('Auto Drain & Refill')
                    ->helperText('Drains the tray during the scheduled period, then auto-refills it'),

                TextInput::make('configuration.settings.waterChangeCycle')
                    ->label('Drain & Refill Cycle')
                    ->numeric()
                    ->minValue(1)
                    ->suffix('days'),

                TimePicker::make('configuration.settings.waterChangeTime')
                    ->label('Drain & Refill Time')
                    ->seconds(false)
                    ->formatStateUsing(fn($state) => Time::toTimeFromSeconds((int) ($state ?? 0)))
                    ->dehydrateStateUsing(fn($state) => Time::toSeconds($state)),

                Toggle::make('configuration.settings.cleanWaterLackLight')
                    ->label('Clean Water Tank Low Light'),

                Toggle::make('configuration.settings.cleanWaterEmptyLight')
                    ->label('Clean Water Tank Empty Light'),

                Toggle::make('configuration.settings.wasteWaterFullLight')
                    ->label('Waste Water Tank Full Light'),
            ]),

            Section::make('Flow (Fountain)')->columns(2)->schema([
                Select::make('configuration.settings.fountainMode')
                    ->label('Flow Mode')
                    ->helperText('Option order inferred from the app UI: Off / Continuous / Interval / Sensor')
                    ->options([0 => 'Off', 1 => 'Continuous', 2 => 'Interval', 3 => 'Sensor']),

                TextInput::make('configuration.settings.fountainTime')
                    ->label('Flow Run Time Preset')
                    ->numeric()
                    ->minValue(1),

                Select::make('configuration.settings.flushIntensity')
                    ->label('Flushing Intensity')
                    ->options([1 => 1, 2 => 2, 3 => 3]),

                Toggle::make('configuration.settings.autoFlush')
                    ->label('Auto Drain & Flush'),

                TimePicker::make('configuration.settings.flushTime')
                    ->label('Drain & Flush Time')
                    ->seconds(false)
                    ->formatStateUsing(fn($state) => Time::toTimeFromSeconds((int) ($state ?? 0)))
                    ->dehydrateStateUsing(fn($state) => Time::toSeconds($state)),

                TextInput::make('configuration.settings.flushCycle')
                    ->label('Drain & Flush Cycle')
                    ->numeric()
                    ->minValue(1),

                TextInput::make('configuration.settings.sleepTime')
                    ->label('Sleep Time Preset')
                    ->numeric()
                    ->minValue(1),
            ]),

            Section::make('Heater')->columns(2)->schema([
                Toggle::make('configuration.settings.heaterSwitch')
                    ->label('Heater'),

                TextInput::make('configuration.settings.heaterTemp')
                    ->label('Heater Target Temperature')
                    ->helperText('Stored in deci-Celsius on the device, displayed here in °C')
                    ->formatStateUsing(fn($state) => $state !== null ? $state / 10 : null)
                    ->dehydrateStateUsing(fn($state) => (int) round($state * 10))
                    ->numeric()
                    ->suffix('°C'),
            ]),

            Section::make('Indicator Light')->columns(2)->schema([
                Toggle::make('configuration.settings.lightMode')
                    ->label('Light Mode')
                    ->helperText('Indicator light works within the following period'),

                Repeater::make('configuration.settings.lightMultiRange')
                    ->columns(2)
                    ->columnSpanFull()
                    ->label('Active Period')
                    ->schema([
                        TimePicker::make('0')->label('From')->seconds(false)->required()
                            ->formatStateUsing(fn(?string $state) => Time::toTimeFromMinutes((int)$state))
                            ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                        TimePicker::make('1')->label('Till')->seconds(false)->required()
                            ->formatStateUsing(fn(?string $state) => Time::toTimeFromMinutes((int)$state))
                            ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                    ]),
            ]),

            Section::make('Voice Settings')->columns(2)->schema([
                TextInput::make('configuration.settings.volume')
                    ->label('Volume')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                Toggle::make('configuration.settings.systemSoundEnable')->label('System Sound'),

                Fieldset::make('Do not Disturb')->columns(2)->schema([
                    Toggle::make('configuration.settings.disturbMode')
                        ->label('Do not disturb')
                        ->helperText('The fountain is turned off while do-not-disturb is active'),
                    Repeater::make('configuration.settings.distrubMultiRange')
                        ->columns(2)
                        ->label('Undisturbed Period')
                        ->schema([
                            TimePicker::make('0')->label('From')->seconds(false)
                                ->formatStateUsing(fn($state) => Time::toTimeFromMinutes((int)$state))
                                ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                            TimePicker::make('1')->label('Till')->seconds(false)
                                ->formatStateUsing(fn($state) => Time::toTimeFromMinutes((int)$state))
                                ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                        ]),

                    Toggle::make('configuration.settings.toneMode')->label('Prompt Tone Do not disturb'),
                    Repeater::make('configuration.settings.toneMultiRange')
                        ->columns(2)
                        ->label('Undisturbed Period')
                        ->schema([
                            TimePicker::make('0')->label('From')->seconds(false)
                                ->formatStateUsing(fn($state) => Time::toTimeFromMinutes((int)$state))
                                ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                            TimePicker::make('1')->label('Till')->seconds(false)
                                ->formatStateUsing(fn($state) => Time::toTimeFromMinutes((int)$state))
                                ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                        ]),

                    Toggle::make('configuration.settings.wlDisturbMode')
                        ->label('Water Level Alert Do not disturb')
                        ->helperText("The indicator light won't turn on during this period"),
                    Repeater::make('configuration.settings.wlDisturbMultiRange')
                        ->columns(2)
                        ->label('Undisturbed Period')
                        ->schema([
                            TimePicker::make('0')->label('From')->seconds(false)
                                ->formatStateUsing(fn($state) => Time::toTimeFromMinutes((int)$state))
                                ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                            TimePicker::make('1')->label('Till')->seconds(false)
                                ->formatStateUsing(fn($state) => Time::toTimeFromMinutes((int)$state))
                                ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                        ]),

                    Toggle::make('configuration.settings.awDisturbMode')
                        ->label('Add Water Alert Do not disturb')
                        ->helperText('Auto-refill is disabled during this period'),
                    Repeater::make('configuration.settings.awDisturbMultiRange')
                        ->columns(2)
                        ->label('Undisturbed Period')
                        ->schema([
                            TimePicker::make('0')->label('From')->seconds(false)
                                ->formatStateUsing(fn($state) => Time::toTimeFromMinutes((int)$state))
                                ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                            TimePicker::make('1')->label('Till')->seconds(false)
                                ->formatStateUsing(fn($state) => Time::toTimeFromMinutes((int)$state))
                                ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
                        ]),
                ])
            ]),

            Section::make('Settings')->columns(2)->schema([
                Toggle::make('configuration.settings.manualLock')
                    ->helperText('Activate Child Lock to disable the control panel')
                    ->label('Child Lock'),

                Toggle::make('configuration.settings.upload')
                    ->label('Cloud Recording'),
            ]),

            Section::make('Unknown')->columns(3)->schema([
                ViewField::make('UnknownWarning')
                    ->columnSpanFull()
                    ->view('filament.forms.warning')
                    ->viewData(['message' => 'Its Unknown, because the changes are not verified']),

                Toggle::make('configuration.settings.timestamp_enable')
                    ->label('Timestamp Display')
                    ->helperText('Local config file only - not confirmed remotely settable'),

                Toggle::make('configuration.settings.preLive')
                    ->label('Pre-roll Live Buffer')
                    ->helperText('Local config file only - not confirmed remotely settable'),

                Toggle::make('configuration.settings.log_upload')
                    ->label('Log Upload')
                    ->helperText('Local config file only - not confirmed remotely settable'),
            ]),
        ];
    }

}
