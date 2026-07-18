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
                Forms\Components\TextInput::make('configuration.consumables.cardboardDurability')
                    ->label('Cardboard Durability (days)')
                    ->numeric(),
                Forms\Components\TextInput::make('configuration.consumables.cardboardNextChange')
                    ->label('Next Reset in Days (Cardboard)')
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
                    ->helperText('No video footage will be generated if you turn this feature off, but autop cleaning will still work')
                    ->label('Camera Switch'),

                    Forms\Components\Toggle::make('configuration.settings.microphone')
                        ->helperText('Enable/Disable sound collection')
                        ->label('Microphone'),

                    Forms\Components\Toggle::make('configuration.settings.microlight')
                        ->helperText('EWhen enabled, the indicator light is ON')
                        ->label('Microphone Indicator Light'),


                    Forms\Components\Toggle::make('configuration.settings.night')
                        ->helperText('Enable infrared night vision in dark environment')
                        ->label('Night Vision'),

                    Forms\Components\Toggle::make('configuration.settings.timeDisplay')
                        ->label('Timestamp Display'),


                    Forms\Components\Toggle::make('configuration.settings.cameraLight')
                        ->label('Camera Light'),


            ]),

            Forms\Components\Section::make('Smart Detection')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.petDetection')
                    ->helperText('For auto detection of pet movement')
                    ->label('Pet Appearance Detection'),

                Forms\Components\Toggle::make('configuration.settings.toiletDetection')
                    ->helperText('For videos of pet toileting')
                    ->label('Toilet Video Recording')
                ]),


            Forms\Components\Section::make('Cleaning')->columns(2)->schema([

                Forms\Components\Toggle::make('configuration.settings.kitten')
                    ->helperText('Disable auto and periodical cleaning')
                    ->label('Kitten Protection'),

                Forms\Components\Toggle::make('configuration.settings.autoWork')
                    ->helperText('After the pet leaves, the device starts automatic cleaning')
                    ->label('Auto Cleaning'),

                Forms\Components\Select::make('configuration.settings.stillTime')
                    ->label('Delayed Cleaning')
                    ->helperText('Set time interval before device starts cleaning')
                    ->options([
                        1200 => '20 Minutes',
                        1800 => '30 Minutes',
                        2400 => '40 Minutes',
                        3000 => '50 Minutes',
                        3600 => '60 Minutes',
                    ]),

                Forms\Components\Toggle::make('configuration.settings.avoidRepeat')
                    ->helperText('Avoid repeated cleaning within a short period')
                    ->label('Avoid Repeated Cleaning')
            ]),

            Forms\Components\Section::make('Deodorize')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.autoSpray')
                    ->helperText('No auto-deodorize after pet use or before cleaning')
                    ->label('Auto Deodorizing'),

                Forms\Components\Toggle::make('configuration.settings.deepSpray')
                    ->helperText('The device will deodorize twice consecutively each time')
                    ->label('Deep Deodorizing'),
            ]),

            Forms\Components\Section::make('Lights')->columns(2)->schema([


                Forms\Components\Toggle::make('configuration.settings.toiletLightAssist')
                    ->helperText('Once turned off, no toileting event playback is available in low light')
                    ->label('Light Assist for Toileting'),

                Forms\Components\Toggle::make('configuration.settings.lightAssist')
                    ->helperText('Once turned off, no toileting event playback is available in low light')
                    ->label('Light Assist for Cleaning'),

            ]),

            Forms\Components\Section::make('General')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.manualLock')
                    ->helperText('Activate the child lock to disable the control panel')
                    ->label('Child Lock'),

                Forms\Components\Toggle::make('configuration.settings.upload')
                    ->helperText('Upload events and media to the cloud')
                    ->label('Cloud Upload'),


                Forms\Components\Toggle::make('configuration.settings.wifiLightAssist')
                    ->helperText('The indicator light for Wi-Fi, cleaning, and the litter tray will turn on during your set period')
                    ->label('Indicator Light'),
            ]),

            Forms\Components\Section::make('Health Monitoring')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.voice')
                    ->helperText('Health monitoring for voice')
                    ->label('Yowling Detection'),

                Forms\Components\Toggle::make('configuration.settings.urine')
                    ->helperText('Require the right litter')
                    ->label('Urine pH Detection'),

                Forms\Components\Toggle::make('configuration.settings.softMode')
                    ->label('Loose Stool Recognition'),

                Forms\Components\Toggle::make('configuration.settings.softModeClean')
                    ->helperText('Requires Loose Stool Recognition')
                    ->label('Do not Clean Loose Stool'),
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
