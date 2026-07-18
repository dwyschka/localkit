<?php

namespace App\Petkit\UI;

use App\Helpers\Time;
use App\Management\Go2RTC;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TimePicker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Filament\Forms;

class PetkitPurobotCrystal
{

    public function formFields(): array
    {
        return [
            Forms\Components\Section::make('Media')->schema([
                Forms\Components\View::make('camera_stream')->viewData(fn($record): array => [
                    'stream' => app(Go2RTC::class)->streamUrl($record)
                ])
                    ->hidden(fn($record) => is_null($record->configuration()->ipAddress))
                    ->columnSpan('full'),

                Forms\Components\Placeholder::make('Snapshot')
                    ->content(function ($record) {
                        $image = $record->configuration()->lastSnapshot;
                        if (is_null($image)) {
                            return '';
                        }
                        $blob = Storage::disk('snapshots')->get($image);
                        if (is_null($blob)) {
                            return '';
                        }
                        return new HtmlString(sprintf('<img src="data:image/jpeg;base64,%s" />', base64_encode($blob)));
                    })
                    ->hidden(fn($record) => is_null($record->configuration()->lastSnapshot))
            ])->collapsible()
                ->hidden(fn($record) => is_null($record->configuration()->ipAddress) || !$record->mqtt_connected),

            Forms\Components\Section::make('Consumables')->columns(2)->schema([
                Forms\Components\TextInput::make('configuration.consumables.n60Durability')
                    ->label('N60 Durability (days)')
                    ->numeric(),
                Forms\Components\TextInput::make('configuration.consumables.n60NextChange')
                    ->label('Next Reset in Days (N60)')
                    ->formatStateUsing(function ($state) {
                        if ($state <= 0) {
                            return 'Not set';
                        }
                        $date = Carbon::parse($state);
                        $now = Carbon::now();

                        return round($now->diffInDays($date));
                    })->readOnly()->disabled(true),
            ]),

            Forms\Components\Section::make('Camera Settings')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.camera')
                    ->helperText('Turning off the camera disables live streaming, playback and other video related functions')
                    ->label('Camera Switch'),

                Forms\Components\Toggle::make('configuration.settings.microphone')
                    ->helperText('Enable/Disable sound collection')
                    ->label('Microphone'),

                Forms\Components\Toggle::make('configuration.settings.night')
                    ->helperText('Enable infrared night vision in dark environments')
                    ->label('Night Vision'),

                Forms\Components\Toggle::make('configuration.settings.timestamp_enable')
                    ->label('Timestamp Display'),

                Forms\Components\Toggle::make('configuration.settings.microlight')
                    ->label('Microphone Light'),

                Forms\Components\Toggle::make('configuration.settings.cameraLight')
                    ->label('Camera Light'),

                Forms\Components\Toggle::make('configuration.settings.preLive')
                    ->helperText('Keep a short preview stream ready')
                    ->label('Preview Live'),

                $this->multiRangeRepeater('configuration.settings.cameraMultiRange.ranges', 'Camera Active Period')
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Detection')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.petDetection')
                    ->helperText('For events of a pet visiting the toilet')
                    ->label('Pet Visit Detection'),

                Forms\Components\Toggle::make('configuration.settings.toiletDetection')
                    ->label('Toilet Detection'),

                Forms\Components\Toggle::make('configuration.settings.moveDetection')
                    ->helperText('For events of a pet moving before the camera')
                    ->label('Move Detection'),

                Forms\Components\Select::make('configuration.settings.moveSensitivity')
                    ->label('Move Sensitivity')
                    ->options(array_combine(range(0, 9), range(0, 9))),

                Forms\Components\TextInput::make('configuration.settings.detectInterval')
                    ->label('Detection Interval')
                    ->numeric(),
            ]),

            Forms\Components\Section::make('Cleaning')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.autoWork')
                    ->helperText('After the pet leaves, the device starts automatic cleaning')
                    ->label('Auto Cleaning'),

                Forms\Components\Toggle::make('configuration.settings.avoidRepeat')
                    ->helperText('Avoid repeated cleaning within a short period')
                    ->label('Avoid Repeated Cleaning'),

                Forms\Components\Toggle::make('configuration.settings.underweight')
                    ->helperText('Disable auto cleaning when the detected weight is too light')
                    ->label('Light Weight Protection'),

                Forms\Components\Toggle::make('configuration.settings.kitten')
                    ->helperText('Disable auto and periodical cleaning')
                    ->label('Kitten Protection'),

                Forms\Components\Toggle::make('configuration.settings.bury')
                    ->label('Waste Covering'),

                Forms\Components\Toggle::make('configuration.settings.deepClean')
                    ->label('Deep Cleaning'),

                Forms\Components\Toggle::make('configuration.settings.downpos')
                    ->helperText('Cylinder rotates from its initial level and wont pause during the process')
                    ->label('Uninterrupted Rotation'),

                Forms\Components\Select::make('configuration.settings.sandType')
                    ->label('Litter Type')
                    ->options([
                        '0' => 'Sand',
                        '1' => 'Betonit/Mineral',
                        '2' => 'Tofu',
                    ]),

                Forms\Components\Select::make('configuration.settings.unit')
                    ->label('Unit')
                    ->options([
                        '0' => 'Kilogram',
                        '1' => 'Pound',
                    ]),

                Forms\Components\TextInput::make('configuration.settings.stillTime')
                    ->label('Still Time (seconds)')
                    ->helperText('Delay before cleaning after the pet leaves')
                    ->numeric(),

                Forms\Components\TextInput::make('configuration.settings.autoIntervalMin')
                    ->label('Auto Interval (min)')
                    ->numeric(),

                Forms\Components\TextInput::make('configuration.settings.sandTrayStandardDay')
                    ->label('Litter Replacement Cycle (days)')
                    ->numeric(),
            ]),

            Forms\Components\Section::make('Health Monitoring')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.urine')
                    ->label('Urine Detection'),

                Forms\Components\Toggle::make('configuration.settings.occult')
                    ->helperText('Health monitoring for occult blood')
                    ->label('Occult Blood Detection'),

                Forms\Components\Toggle::make('configuration.settings.softMode')
                    ->label('Soft Mode'),
            ]),

            Forms\Components\Section::make('Lights')->schema([
                Forms\Components\Toggle::make('configuration.settings.lightMode')
                    ->helperText('Indicator light works within the following period')
                    ->label('Indicator Light'),
                $this->multiRangeRepeater('configuration.settings.lightMultiRange.ranges', 'Indicator Light Period'),

                Forms\Components\Toggle::make('configuration.settings.lightAssist')
                    ->label('Ambient Light Assist'),
                $this->multiRangeRepeater('configuration.settings.lightAssistMultiRange.ranges', 'Ambient Light Period'),

                Forms\Components\Toggle::make('configuration.settings.toiletLightAssist')
                    ->label('Toilet Light Assist'),
                $this->multiRangeRepeater('configuration.settings.toiletLightAssistMultiRange.ranges', 'Toilet Light Period'),

                Forms\Components\Toggle::make('configuration.settings.wifiLightAssist')
                    ->label('WiFi Light Assist'),
                $this->multiRangeRepeater('configuration.settings.wifiLightAssistMultiRange.ranges', 'WiFi Light Period'),
            ])->collapsible()->collapsed(),

            Forms\Components\Section::make('Sound')->columns(2)->schema([
                Forms\Components\Select::make('configuration.settings.volume')
                    ->columnSpanFull()
                    ->label('Volume')
                    ->options(array_combine(range(0, 9), range(0, 9))),

                Forms\Components\Toggle::make('configuration.settings.systemSoundEnable')->label('Voice Prompt'),
                Forms\Components\Toggle::make('configuration.settings.soundEnable')->label('Voice for Cleaning'),

                Forms\Components\Fieldset::make('Do not Disturb (Tone)')->columns(1)->schema([
                    Forms\Components\Toggle::make('configuration.settings.toneMode')->label('Silence Tones'),
                    Forms\Components\Hidden::make('configuration.settings.toneMultiRange.name')->default('toneMultiRange'),
                    $this->multiRangeRepeater('configuration.settings.toneMultiRange.ranges', 'Undisturbed Period'),
                ]),

                Forms\Components\Fieldset::make('Do not Disturb')->columns(1)->schema([
                    Forms\Components\Toggle::make('configuration.settings.disturbMode')->label('Do not disturb'),
                    Forms\Components\Hidden::make('configuration.settings.distrubMultiRange.name')->default('distrubMultiRange'),
                    $this->multiRangeRepeater('configuration.settings.distrubMultiRange.ranges', 'Undisturbed Period'),
                ]),
            ]),

            Forms\Components\Section::make('General')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.manualLock')
                    ->helperText('Activate the child lock to disable the control panel')
                    ->label('Child Lock'),

                Forms\Components\Toggle::make('configuration.settings.upload')
                    ->helperText('Upload events and media to the cloud')
                    ->label('Cloud Upload'),

                Forms\Components\Toggle::make('configuration.settings.shareOpen')->label('Share Open'),
                Forms\Components\Toggle::make('configuration.settings.multiConfig')->label('Multi Config'),
            ]),
        ];
    }

    private function multiRangeRepeater(string $path, string $label): Repeater
    {
        return Repeater::make($path)
            ->columns(2)
            ->label($label)
            ->schema([
                TimePicker::make('from')
                    ->label('From')
                    ->seconds(false)
                    ->required()
                    ->formatStateUsing(fn(?string $state) => Time::toTimeFromMinutes((int)$state))
                    ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),

                TimePicker::make('till')
                    ->label('Till')
                    ->seconds(false)
                    ->required()
                    ->formatStateUsing(fn(?string $state) => Time::toTimeFromMinutes((int)$state))
                    ->dehydrateStateUsing(fn($state) => Time::toMinutes($state)),
            ]);
    }
}
