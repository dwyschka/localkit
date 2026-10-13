<?php

namespace App\Petkit\Devices\PurobotUltra;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\View;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use App\Helpers\Time;
use App\Management\Rtsp;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Filament\Forms;
use App\Filament\Forms\Components\MinutesTimePicker;

class UI
{

    public function formFields(): array
    {
        return [
            Section::make('Stats')->schema([
                TextInput::make('configuration.states.ipAddress')
                    ->label('IP Address')
                    // Editable, because the address is parsed out of the
                    // device's own state report and some firmwares never put
                    // it there - without it there is no camera stream and no
                    // telnet. A value set here is used until the device
                    // reports one itself, which then wins.
                    ->helperText('Normally reported by the device. Set it by hand if it stays empty or wrong - the device overwrites it as soon as it reports an address itself.')
                    ->ip()
                    ->placeholder('192.168.1.42'),
            ]),
            Section::make('Media')->schema([
                View::make('camera_stream')->viewData(fn($record): array => [
                    'live' => app(Rtsp::class)->liveUrl($record),
                    'poster' => app(Rtsp::class)->thumbnailUrl($record),
                    'rtsp' => app(Rtsp::class)->url($record),
                ])
                    ->hidden(fn($record) => !app(Rtsp::class)->available($record))
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
                        return new HtmlString(sprintf('<img src="data:image/jpeg;base64,%s" />', base64_encode($blob)));
                    })
                    ->hidden(fn($record) => is_null($record->configuration()->lastSnapshot))
            ])->collapsible()
                ->hidden(fn($record) => is_null($record->configuration()->ipAddress) || !$record->mqtt_connected),

            Section::make('Camera Settings')->columns(2)->schema([
                Toggle::make('configuration.settings.camera')
                    ->helperText('No video footage will be generated if you turn this feature off, but autop cleaning will still work')
                    ->label('Camera Switch'),

                    Toggle::make('configuration.settings.microphone')
                        ->helperText('Enable/Disable sound collection')
                        ->label('Microphone'),


                    Toggle::make('configuration.settings.night')
                        ->helperText('Enable infrared night vision in dark environment')
                        ->label('Night Vision'),

                    Toggle::make('configuration.settings.timestamp_enable')
                        ->helperText('Burns the clock into the video. This device calls the setting timestamp_enable, the T7 calls it timeDisplay')
                        ->label('Timestamp Display'),


                    Toggle::make('configuration.settings.cameraLight')
                        ->label('Camera Light'),

                    Toggle::make('configuration.settings.tumbling')
                        ->helperText('Sets the Tumbling for Camera')
                        ->label('Tumbling'),


            ]),

            Section::make('Smart Detection')->columns(2)->schema([
                Toggle::make('configuration.settings.petDetection')
                    ->helperText('For auto detection of pet movement')
                    ->label('Pet Appearance Detection'),

                Toggle::make('configuration.settings.wanderDetection')
                    ->helperText('Events for a pet loitering around the box without using it')
                    ->label('Wander Detection'),

                Toggle::make('configuration.settings.toiletDetection')
                    ->helperText('For videos of pet toileting')
                    ->label('Toilet Video Recording'),

                Toggle::make('configuration.settings.moveDetection')
                    ->helperText('For events of movement before the camera')
                    ->label('Move Detection'),

                Select::make('configuration.settings.moveSensitivity')
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


            Section::make('Cleaning')->columns(2)->schema([

                Toggle::make('configuration.settings.kitten')
                    ->helperText('Disable auto and periodical cleaning')
                    ->label('Kitten Protection'),

                Toggle::make('configuration.settings.autoWork')
                    ->helperText('After the pet leaves, the device starts automatic cleaning')
                    ->label('Auto Cleaning'),

                Select::make('configuration.settings.stillTime')
                    ->label('Delayed Cleaning')
                    ->helperText('Set time interval before device starts cleaning')
                    ->options([
                        1200 => '20 Minutes',
                        1800 => '30 Minutes',
                        2400 => '40 Minutes',
                        3000 => '50 Minutes',
                        3600 => '60 Minutes',
                    ]),

                Toggle::make('configuration.settings.avoidRepeat')
                    ->helperText('Avoid repeated cleaning within a short period')
                    ->label('Avoid Repeated Cleaning')
            ]),

            Section::make('Reminders')->columns(2)->schema([
                Toggle::make('configuration.settings.bag_tip_en')
                    ->helperText('Remind when the waste bag needs changing')
                    ->label('Bag Reminder'),

                Toggle::make('configuration.settings.spary_tip_en')
                    ->helperText("Remind to refill the spray. The wire key keeps the firmware's own misspelling")
                    ->label('Spray Reminder'),
            ]),

            Section::make('Lights')->columns(2)->schema([


                Toggle::make('configuration.settings.toiletLight')
                    ->label('Toilet Light'),

                Toggle::make('configuration.settings.lightAssist')
                    ->helperText('Once turned off, no toileting event playback is available in low light')
                    ->label('Light Assist for Cleaning'),

            ]),

            Section::make('General')->columns(2)->schema([
                Toggle::make('configuration.settings.manualLock')
                    ->helperText('Activate the child lock to disable the control panel')
                    ->label('Child Lock'),

                Toggle::make('configuration.settings.clickOkEnable')
                    ->helperText('The OK button on the device itself')
                    ->label('OK Button'),

                Toggle::make('configuration.settings.sandSaving')
                    ->label('Litter Saving'),

                Toggle::make('configuration.settings.log_upload')
                    ->helperText('Upload device logs for diagnostics')
                    ->label('Upload Logs'),
            ]),

            Section::make('Home Mode')
                ->description('What the device suppresses while it believes you are home. '
                    . 'Takes effect only while home mode is actually active on the device.')
                ->columns(2)
                ->schema([
                    Select::make('configuration.settings.userHomeMode.homeMode')
                        ->label('Home Mode')
                        ->helperText('Off leaves the three overrides below without effect')
                        ->options([
                            -1 => 'Not configured',
                            0 => 'Off',
                            1 => 'On',
                        ]),

                    Select::make('configuration.settings.userHomeMode.camera')
                        ->label('Camera while home')
                        ->helperText('The device stores this as an off-switch: On here means the camera is turned OFF while you are home')
                        ->options([
                            -1 => 'Not configured (camera stays on)',
                            0 => 'Camera stays on',
                            1 => 'Camera off while home',
                        ]),

                    Select::make('configuration.settings.userHomeMode.mic')
                        ->label('Microphone while home')
                        ->helperText("Stored as the firmware's `noSound`: On here means muted while you are home")
                        ->options([
                            -1 => 'Not configured (sound stays on)',
                            0 => 'Sound stays on',
                            1 => 'Muted while home',
                        ]),

                    Select::make('configuration.settings.userHomeMode.ptz')
                        ->label('Pan motor while home')
                        ->helperText('Locks the camera in place. Only the value 1 locks it; anything else leaves it free')
                        ->options([
                            -1 => 'Not configured (motor free)',
                            0 => 'Motor free',
                            1 => 'Motor locked while home',
                        ]),
                ]),

            Section::make('Health Monitoring')->columns(2)->schema([
                Toggle::make('configuration.settings.voice')
                    ->helperText('Health monitoring for voice')
                    ->label('Yowling Detection'),

                Toggle::make('configuration.settings.phDetection')
                    ->helperText('pH measurement of the urine')
                    ->label('pH Detection'),

                Toggle::make('configuration.settings.softMode')
                    ->label('Loose Stool Recognition'),
            ]),


        ];
    }

    private function multiRangeRepeater(string $path, string $label): Repeater
    {
        return Repeater::make($path)
            ->columns(2)
            ->label($label)
            ->schema([
                MinutesTimePicker::make('from')
                    ->label('From')
                    ->required(),

                MinutesTimePicker::make('till')
                    ->label('Till')
                    ->required(),
            ]);
    }
}
