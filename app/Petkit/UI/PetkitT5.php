<?php

namespace App\Petkit\UI;

use App\Management\Go2RTC;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Filament\Forms;

class PetkitT5
{

    public function formFields(): array
    {
        return [
            Forms\Components\Section::make('Media')->schema([
                Forms\Components\View::make('camera_stream')->viewData(fn($record): array => [
                    'streams' => app(Go2RTC::class)->streamUrls($record)
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

            Forms\Components\Section::make('Camera Settings')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.camera')
                    ->helperText('No video footage will be generated if you turn this feature off, but autop cleaning will still work')
                    ->label('Camera Switch'),

                Forms\Components\Toggle::make('configuration.settings.microphone')
                    ->helperText('Enable/Disable sound collection')
                    ->label('Microphone'),

                Forms\Components\Toggle::make('configuration.settings.night')
                    ->helperText('Enable infrared night vision in dark environment')
                    ->label('Night Vision'),

                Forms\Components\Toggle::make('configuration.settings.timeDisplay')
                    ->label('Timestamp Display'),

                Forms\Components\Toggle::make('configuration.settings.cameraLight')
                    ->label('Camera Light'),

                Forms\Components\Toggle::make('configuration.settings.toiletLight')
                    ->label('Toilet Light'),

                Forms\Components\Toggle::make('configuration.settings.tumbling')
                    ->helperText('Sets the Tumbling for Camera')
                    ->label('Tumbling'),

            ]),

            Forms\Components\Section::make('Smart Detection')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.petDetection')
                    ->helperText('For auto detection of pet movement')
                    ->label('Pet Appearance Detection'),

                Forms\Components\Select::make('configuration.settings.petSensitivity')
                    ->helperText('Sensitivity for pet appearance detection')
                    ->label('Pet Appearance Sensitivity')
                    ->options([
                        0 => 0,
                        1 => 1,
                        2 => 2,
                        3 => 3,
                        4 => 4,
                        5 => 5,
                        6 => 6,
                        7 => 7,
                        8 => 8,
                        9 => 9,
                    ]),

                Forms\Components\Toggle::make('configuration.settings.toiletDetection')
                    ->helperText('For videos of pet toileting')
                    ->label('Toilet Video Recording'),

                Forms\Components\Toggle::make('configuration.settings.moveDetection')
                    ->helperText('For events of movement before the camera')
                    ->label('Move Detection'),

                Forms\Components\Select::make('configuration.settings.moveSensitivity')
                    ->helperText('Sensitivity for movement events before the camera')
                    ->label('Move Sensitivity')
                    ->options([
                        0 => 0,
                        1 => 1,
                        2 => 2,
                        3 => 3,
                        4 => 4,
                        5 => 5,
                        6 => 6,
                        7 => 7,
                        8 => 8,
                        9 => 9,
                    ]),
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
                        120 => '2 Minutes',
                        1200 => '20 Minutes',
                        1800 => '30 Minutes',
                        2400 => '40 Minutes',
                        3000 => '50 Minutes',
                        3600 => '60 Minutes',
                    ]),

                Forms\Components\Toggle::make('configuration.settings.avoidRepeat')
                    ->helperText('Avoid repeated cleaning within a short period')
                    ->label('Avoid Repeated Cleaning'),

                Forms\Components\Toggle::make('configuration.settings.sandSaving')
                    ->helperText('Reduce litter usage during automatic cleaning')
                    ->label('Litter Saving'),
            ]),

            Forms\Components\Section::make('Deodorize')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.autoSpray')
                    ->helperText('Auto-deodorize after pet use or before cleaning')
                    ->label('Auto Deodorizing'),

                Forms\Components\Toggle::make('configuration.settings.deepSpray')
                    ->helperText('The device will deodorize twice consecutively each time')
                    ->label('Deep Deodorizing'),

                Forms\Components\TextInput::make('configuration.settings.sprayDays')
                    ->helperText('Deodorant cartridge refill cycle in days')
                    ->label('Deodorant Refill Cycle')
                    ->numeric(),
            ]),

            Forms\Components\Section::make('Lights')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.lightAssist')
                    ->helperText('Turns on the light to assist cleaning/toileting playback in low light')
                    ->label('Light Assist for Cleaning'),
            ]),

            Forms\Components\Section::make('General')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.manualLock')
                    ->helperText('Activate the child lock to disable the control panel')
                    ->label('Child Lock'),

                Forms\Components\Toggle::make('configuration.settings.clickOkEnable')
                    ->helperText('Play a confirmation sound on button press')
                    ->label('Confirm Click Sound'),
            ]),

            Forms\Components\Section::make('Health Monitoring')->columns(2)->schema([
                Forms\Components\Toggle::make('configuration.settings.voice')
                    ->helperText('Health monitoring for voice')
                    ->label('Yowling Detection'),

                Forms\Components\Toggle::make('configuration.settings.phDetection')
                    ->helperText('Urine pH detection/measurement on/off')
                    ->label('Urine pH Detection'),

                Forms\Components\Toggle::make('configuration.settings.softMode')
                    ->label('Loose Stool Recognition'),
            ]),

        ];
    }
}
