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
            Section::make('Consumables')->columns(2)->schema([
                TextInput::make('configuration.consumables.desiccantDurability')
                    ->label('Desiccant Durability (days)')
                    ->numeric(),
                TextInput::make('configuration.consumables.desiccantNextChange')
                    ->label('Next Desiccant Change')
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

            ]),

            Section::make('Smart Detection')->columns(2)->schema([
                Toggle::make('configuration.settings.petDetection')
                    ->helperText('Detect when a pet appears in the frame')
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

                TextInput::make('configuration.settings.detectInterval')
                    ->helperText('Detection period')
                    ->label('Detection interval')
                    ->numeric(),
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
                        '0' => 'Off',
                        '30' => '30 seconds',
                        '60' => '1 Minute',
                        '300' => '5 Minutes',
                        '600' => '10 Minutes',
                        '900' => '15 Minutes',
                        '1800' => '30 Minutes',
                        '3600' => '1 Hour',
                    ]),

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

                Toggle::make('configuration.settings.avoidRepeat')
                    ->helperText('Set based on cleaning interval or pet weight')
                    ->label('Avoid Repeated Cleaning'),

                Toggle::make('configuration.settings.underweight')
                    ->helperText("When the device detects that the pet's weight is less than the actual minimum weight, it will not start automatic cleaning")
                    ->label('Disable auto cleaning for light weight'),

                TextInput::make('configuration.settings.lightest')
                    ->label('Minimum Weight'),

                Toggle::make('configuration.settings.deepClean')
                    ->helperText('After being enabled, it can effectively remove the waste residue')
                    ->label('Deep Cleaning'),

                Toggle::make('configuration.settings.bury')
                    ->helperText('Help evenly cover waste with cat litter before cleaning cycle')
                    ->label('Waste Covering'),

                Toggle::make('configuration.settings.sandSaving')
                    ->helperText('Turn it on when clumps are relatively large')
                    ->label('Litter-saving Mode'),

                Select::make('configuration.settings.sandType')
                    ->label('Litter Type')
                    ->helperText('Resolved from OverseaToiletLitterSettingFragment: the app is 1-based, 0 = not set yet')
                    ->options([
                        '0' => 'Not set',
                        '1' => 'Bentonite / Ore',
                        '2' => 'Tofu',
                        '3' => 'Mixed',
                    ]),

                Select::make('configuration.settings.unit')
                    ->label('Weight Unit')
                    ->options([
                        '0' => 'Kg',
                        '1' => 'Pound',
                    ]),
            ]),

            Section::make('Do Not Disturb')->columns(2)->schema([
                Toggle::make('configuration.settings.disturbMode')
                    ->helperText("The device doesn't work during the do-not-disturb period")
                    ->label('Do Not Disturb'),

                $this->multiRangeRepeater('configuration.settings.distrubMultiRange.ranges', 'Undisturbed Period'),
            ]),

            Section::make('Reminders')->columns(2)->schema([
                Toggle::make('configuration.settings.bag_tip_en')
                    ->helperText('Remind when the waste bag needs changing')
                    ->label('Refill Ring Replacement Reminder'),

                Toggle::make('configuration.settings.spary_tip_en')
                    ->helperText("Remind to refill the purifying liquid. The wire key keeps the firmware's own misspelling")
                    ->label('Purifying Liquid Reminder'),
            ]),

            Section::make('Lights')->columns(2)->schema([

                Toggle::make('configuration.settings.toiletLight')
                    ->helperText('The light will turn on automatically when the cat enters')
                    ->label('Light Assist during Toileting'),

                Toggle::make('configuration.settings.lightAssist')
                    ->helperText('The light will turn on automatically during cleaning. Once turned off, no toileting event playback is available in low light')
                    ->label('Light Assist during Cleaning'),

                Toggle::make('configuration.settings.lightMode')
                    ->helperText('After turning it off, the display will automatically turn off when the device is not in operation')
                    ->label('Set Screen Display'),

                $this->multiRangeRepeater('configuration.settings.lightMultiRange.ranges', 'Screen Period'),

            ]),

            Section::make('Voice Settings')->columns(2)->schema([
                Toggle::make('configuration.settings.systemSoundEnable')
                    ->label('Voice Prompt'),

                Toggle::make('configuration.settings.toneMode')
                    ->helperText('The device is muted within the following period.')
                    ->label('Do Not Disturb (Voice)'),

                $this->multiRangeRepeater('configuration.settings.toneMultiRange.ranges', 'Undisturbed Period (Voice)'),
            ]),

            Section::make('General')->columns(2)->schema([
                Toggle::make('configuration.settings.manualLock')
                    ->helperText('Activate the child lock to disable the control panel')
                    ->label('Child Lock'),

                Toggle::make('configuration.settings.clickOkEnable')
                    ->helperText('The OK button on the device itself')
                    ->label('OK Button'),

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
                    ->helperText('Cylinder will rotate several times to have cat litter wrapped thoroughly - cleaning takes longer while this is on')
                    ->label('Loose Stool Mode'),
            ]),

            Section::make('Unverified')->schema([
                Toggle::make('configuration.settings.tumbling')
                    ->helperText('No app screen reference found for this field')
                    ->label('Tumbling'),
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
