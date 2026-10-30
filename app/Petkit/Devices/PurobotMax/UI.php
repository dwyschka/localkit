<?php

namespace App\Petkit\Devices\PurobotMax;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\KeyValue;
use Illuminate\Support\Carbon;
use App\Filament\Forms\Components\MinutesTimePicker;

/**
 * Sections mirror the PetKit app's own settings screens for the T5:
 *
 *  - T6SettingActivity (main screen, deviceType 21, shared with T6/27):
 *    camera switch, basic camera settings, working time, privacy,
 *    intelligent detection, speech, child lock, litter type, unit,
 *    screen light, language, share.
 *  - T6SmartSettingActivity ("Set Clean") for the T5: auto clean, wait
 *    time, interval, avoid-repeat, underweight, kitten, deep clean, bury,
 *    litter saving, toilet light, auto/deep deodorizing, scheduled
 *    deodorizing, do-not-disturb.
 *  - T6LightSettingActivity: toilet light active period.
 *  - T6SpeechSettingsActivity + T6SpeechDoNotDisturbSettingActivity.
 *  - T6WorkingTimeActivity: camera working period and camera light.
 *  - T6IntelligentDetectionActivity + AiPhActivity + AiVoiceActivity.
 *  - KidLockActivity.
 *
 * Fields that ctrl_t5 does not parse at all are shown read-only or under
 * "Unverified" instead of being offered as if they worked - see
 * docs/t5-ctrl-mqtt.md.
 */
class UI
{
    public function formFields(): array
    {
        return [
            Section::make('Consumables')->columns(2)->schema([
                TextInput::make('configuration.consumables.n50Durability')
                    ->label('N50 Durability (days)')
                    ->numeric(),

                TextInput::make('configuration.consumables.n50NextChange')
                    ->label('Next N50 Change')
                    ->formatStateUsing(function ($state) {
                        if ($state <= 0) {
                            return 'Not set';
                        }

                        return round(Carbon::now()->diffInDays(Carbon::parse($state)));
                    })
                    ->readOnly()
                    ->disabled(true),

                TextInput::make('configuration.consumables.n60Durability')
                    ->label('N60 Durability (days)')
                    ->numeric(),

                TextInput::make('configuration.consumables.n60NextChange')
                    ->label('Next N60 Change')
                    ->formatStateUsing(function ($state) {
                        if ($state <= 0) {
                            return 'Not set';
                        }

                        return round(Carbon::now()->diffInDays(Carbon::parse($state)));
                    })
                    ->readOnly()
                    ->disabled(true),
            ]),

            Section::make('Litter')->columns(3)->schema([
                TextInput::make('history_count')
                    ->label('Used Today')
                    ->readOnly()
                    ->formatStateUsing(function ($record) {
                        return $record->histories()
                            ->whereDate('created_at', now()->toDateTime())
                            ->where('type', '=', 'IN_USE')
                            ->count();
                    }),

                TextInput::make('configuration.litter.percent')
                    ->label('Litter Percentage')
                    ->readOnly(),

                TextInput::make('configuration.litter.weight')
                    ->label('Litter Weight')
                    ->readOnly(),
            ]),

            Section::make('Network')->columns(2)->schema([
                TextInput::make('configuration.states.ipAddress')
                    ->label('IP Address')
                    ->helperText('Normally detected from the `other` field of the device state report. Enter it manually if detection is missing or wrong.')
                    ->ip()
                    ->placeholder('192.168.1.42'),

                Toggle::make('configuration.states.ipAddressOverride')
                    ->label('Keep manual IP')
                    ->helperText('When enabled, later state reports cannot overwrite the manually entered IP. Turn it off to let automatic detection take over again.')
                    ->inline(false),
            ]),

            // T6SmartSettingActivity, T5 variant.
            Section::make('Set Clean')->columns(2)->schema([
                Toggle::make('configuration.settings.autoWork')
                    ->label('Auto Cleaning')
                    ->helperText('After the pet leaves, the device starts automatic cleaning.'),

                Select::make('configuration.settings.autoIntervalMin')
                    ->label('Time Interval Of Each Cleaning')
                    ->helperText('During this time the device will not clean repeatedly after multiple visits.')
                    ->options([
                        '0' => 'Do not limit',
                        '300' => '5 minutes',
                        '600' => '10 minutes',
                        '900' => '15 minutes',
                        '1800' => '30 minutes',
                        '2700' => '45 minutes',
                        '3600' => '1 hour',
                        '4500' => '1 hour 15 minutes',
                        '5400' => '1 hour 30 minutes',
                        '6300' => '1 hour 45 minutes',
                        '7200' => '2 hours',
                    ]),

                Select::make('configuration.settings.stillTime')
                    ->label('Delayed Cleaning')
                    ->helperText('Wait time before the device starts cleaning. The firmware value is in seconds.')
                    ->options([
                        '0' => '0 seconds',
                        '30' => '30 seconds',
                        '60' => '1 minute',
                        '120' => '2 minutes',
                        '180' => '3 minutes',
                        '240' => '4 minutes',
                        '300' => '5 minutes',
                        '600' => '10 minutes',
                        '900' => '15 minutes',
                        '1800' => '30 minutes',
                        '2700' => '45 minutes',
                        '3600' => '60 minutes',
                    ]),

                Toggle::make('configuration.settings.avoidRepeat')
                    ->label('Avoid Repeated Cleaning')
                    ->helperText('Set based on cleaning interval or pet weight.'),

                Toggle::make('configuration.settings.underweight')
                    ->label('Disable Auto Cleaning For Light Weight')
                    ->helperText('Disable auto cleaning when the pet is lighter than the minimum weight.'),

                TextInput::make('configuration.settings.lightest')
                    ->label('Minimum Weight (g)')
                    ->numeric(),

                Toggle::make('configuration.settings.kitten')
                    ->label('Kitten Protection')
                    ->helperText('Disables auto and periodic cleaning while a kitten is using the box.'),

                Toggle::make('configuration.settings.deepClean')
                    ->label('Deep Cleaning')
                    ->helperText('Runs an additional, longer cleaning pass.'),

                Toggle::make('configuration.settings.bury')
                    ->label('Waste Covering')
                    ->helperText('Covers waste with litter before the cleaning cycle.'),

                Toggle::make('configuration.settings.tumbling')
                    ->label('Tumbling')
                    ->helperText('Runs the tumbling motor routine; also used by the app\'s "look inside" feature.'),

                Toggle::make('configuration.settings.sandSaving')
                    ->label('Litter Saving')
                    ->helperText('Turn on when clumps are relatively large.'),

                Toggle::make('configuration.settings.fixedTimeClear')
                    ->label('Scheduled Cleaning Enabled')
                    ->helperText('Enables the clean schedule managed in the PetKit app. The schedule itself is stored by PetKit\'s cloud API, not here.'),

                Toggle::make('configuration.settings.toiletLight')
                    ->label('Toilet Light')
                    ->helperText('Turns the light on while the toilet is in use. See "Light Setting" for the active period.'),

                Toggle::make('configuration.settings.autoSpray')
                    ->label('Auto Deodorizing')
                    ->helperText('Deodorizes after the pet uses the box or before cleaning.'),

                Toggle::make('configuration.settings.deepSpray')
                    ->label('Deep Deodorizing')
                    ->helperText('Deodorizes twice consecutively each time.'),

                Toggle::make('configuration.settings.fixedTimeSpray')
                    ->label('Scheduled Deodorizing Enabled')
                    ->helperText('Enables the deodorizing schedule managed in the PetKit app.'),

                Toggle::make('configuration.settings.disturbMode')
                    ->label('Do Not Disturb (Cleaning)')
                    ->helperText('Stops cleaning during the period below.'),

                Repeater::make('configuration.settings.distrubMultiRange.ranges')
                    ->columns(2)
                    ->label('Do Not Disturb Period')
                    ->reorderableWithButtons()
                    ->schema([
                        MinutesTimePicker::make('from'),
                        MinutesTimePicker::make('till'),
                    ]),
            ]),

            // T6LightSettingActivity.
            Section::make('Light Setting')->schema([
                Repeater::make('configuration.settings.lightMultiRange.ranges')
                    ->columns(2)
                    ->label('Toilet Light Active Period')
                    ->reorderableWithButtons()
                    ->schema([
                        MinutesTimePicker::make('from'),
                        MinutesTimePicker::make('till'),
                    ]),
            ]),

            // T6BasicSettingsActivity + T6WorkingTimeActivity.
            Section::make('Camera Settings')->columns(2)->schema([
                Toggle::make('configuration.settings.camera')
                    ->label('Camera Switch')
                    ->helperText('No video is recorded when this is off; cleaning still works.'),

                Toggle::make('configuration.settings.microphone')
                    ->label('Microphone'),

                Toggle::make('configuration.settings.night')
                    ->label('Night Vision')
                    ->helperText('Infrared night vision in dark environments.'),

                Toggle::make('configuration.settings.timeDisplay')
                    ->label('Timestamp Overlay')
                    ->helperText('Burns the clock into the video. ctrl_t5\'s wire key is timeDisplay, not timestamp_enable.'),

                Toggle::make('configuration.settings.cameraLight')
                    ->label('Camera Light'),

                TextInput::make('configuration.settings.detectInterval')
                    ->label('Detection Interval (s)')
                    ->numeric(),

                Repeater::make('configuration.settings.cameraMultiRange.ranges')
                    ->columns(2)
                    ->label('Camera Working Time')
                    ->helperText('From/till in minutes (0 = 00:00, 1440 = 24:00). The T5 applies these windows to every day; ctrl_t5 receives them as its {enable, rpt, time} camera range objects.')
                    ->reorderableWithButtons()
                    ->schema([
                        MinutesTimePicker::make('from'),
                        MinutesTimePicker::make('till'),
                    ])
                    ->columnSpanFull(),
            ]),

            // T6IntelligentDetectionActivity + AiPhActivity + AiVoiceActivity.
            Section::make('Intelligent Detection')->columns(2)->schema([
                Toggle::make('configuration.settings.petDetection')
                    ->label('Pet Appearance Detection'),

                Toggle::make('configuration.settings.toiletDetection')
                    ->label('Toilet Use Detection'),

                Toggle::make('configuration.settings.phDetection')
                    ->label('pH Detection')
                    ->helperText('Urine pH health monitoring (AiPhActivity).'),

                Toggle::make('configuration.settings.voice')
                    ->label('AI Voice Messages')
                    ->helperText('Per-pet AI voice messages (AiVoiceActivity).'),

                Toggle::make('configuration.settings.moveTracking')
                    ->label('Move Tracking')
                    ->helperText('The app\'s activity-detection sensitivity screen is not shown for every T5 firmware; ctrl_t5 still accepts the key.'),

                Select::make('configuration.settings.moveSensitivity')
                    ->label('Move Sensitivity')
                    ->options(array_combine(range(0, 9), range(0, 9))),

                Toggle::make('configuration.settings.petTracking')
                    ->label('Pet Tracking'),

                Select::make('configuration.settings.petSensitivity')
                    ->label('Pet Sensitivity')
                    ->options(array_combine(range(0, 9), range(0, 9))),

                Repeater::make('configuration.settings.detectMultiRange.ranges')
                    ->columns(2)
                    ->label('Detection Period')
                    ->helperText('ctrl_t5 accepts up to five [from,till] pairs here.')
                    ->reorderableWithButtons()
                    ->schema([
                        MinutesTimePicker::make('from'),
                        MinutesTimePicker::make('till'),
                    ])
                    ->columnSpanFull(),
            ]),

            // T6SpeechSettingsActivity + T6SpeechDoNotDisturbSettingActivity.
            Section::make('Speech Settings')->columns(2)->schema([
                Toggle::make('configuration.settings.toneMode')
                    ->label('Voice Prompts Do Not Disturb'),

                Toggle::make('configuration.settings.systemSoundEnable')
                    ->label('System Voice Announcements'),

                Toggle::make('configuration.settings.soundEnable')
                    ->label('Sound'),

                Select::make('configuration.settings.volume')
                    ->label('Volume')
                    ->options(array_combine(range(1, 9), range(1, 9))),

                TextInput::make('configuration.settings.selectedSound')
                    ->label('Selected Sound')
                    ->helperText('Raw device value. The app reads the available list from PetKit\'s server, so this numeric value is not verified.')
                    ->numeric(),

                Repeater::make('configuration.settings.toneMultiRange.ranges')
                    ->columns(2)
                    ->label('Voice Prompts Do Not Disturb Period')
                    ->reorderableWithButtons()
                    ->schema([
                        MinutesTimePicker::make('from'),
                        MinutesTimePicker::make('till'),
                    ])
                    ->columnSpanFull(),
            ]),

            // T6PrivacySettingActivity - only the keys ctrl_t5 actually parses.
            Section::make('Privacy')->columns(2)->schema([
                Toggle::make('configuration.settings.upload')
                    ->label('Upload Videos')
                    ->helperText('Allows cloud upload of event videos.'),

                Toggle::make('configuration.settings.preLive')
                    ->label('Pre-live Preview')
                    ->helperText('The app sends this, but ctrl_t5 has no property parser for preLive - read-only here.')
                    ->disabled()
                    ->dehydrated(false),

                Toggle::make('configuration.settings.shareOpen')
                    ->label('Share Open')
                    ->helperText('Cloud sharing flag, not an MQTT setting.')
                    ->disabled()
                    ->dehydrated(false),
            ]),

            // KidLockActivity.
            Section::make('Child Lock')->columns(2)->schema([
                Toggle::make('configuration.settings.manualLock')
                    ->label('Child Lock')
                    ->helperText('Disables the device buttons during standby.'),

                Toggle::make('configuration.settings.clickOkEnable')
                    ->label('OK Button'),
            ]),

            // T6SettingActivity main screen.
            Section::make('Device')->columns(2)->schema([
                Toggle::make('configuration.settings.lightMode')
                    ->label('Set Screen Display'),

                Toggle::make('configuration.settings.downpos')
                    ->label('Uninterrupted Rotation')
                    ->helperText('Cylinder will rotate from its initial level and will not pause during the process.'),

                Select::make('configuration.settings.unit')
                    ->label('Weight Unit')
                    ->options([
                        '0' => 'Kg',
                        '1' => 'Pound',
                    ]),

                Select::make('configuration.settings.sandType')
                    ->label('Litter Type')
                    ->helperText('The app is 1-based: 0 = not set yet, 1 = bentonite, 2 = tofu, 3 = mixed.')
                    ->options([
                        '0' => 'Not set',
                        '1' => 'Bentonite / Ore',
                        '2' => 'Tofu',
                        '3' => 'Mixed',
                    ]),

                Select::make('configuration.settings.language')
                    ->label('Device Language')
                    ->helperText('Locale string from the app\'s language list. ctrl_t5 accepts it directly.')
                    ->options([
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
                    ]),
            ]),

            Section::make('Schedules (read-only)')->schema([
                Textarea::make('configuration.schedule')
                    ->label('Clean / Deodorize Schedule')
                    ->helperText('ctrl_t5 accepts a `schedule` array of {id, type, time, repeats} items (type 0 = cleaning, 1 = deodorizing). The app manages it through PetKit\'s cloud API; Localkit currently stores it read-only.')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) $state)
                    ->rows(8)
                    ->disabled()
                    ->dehydrated(false),
            ]),

            Section::make('Unknown / Unverified')->schema([
                ViewField::make('warning')
                    ->columnSpanFull()
                    ->view('filament.forms.warning')
                    ->viewData(['message' => 'These fields exist on the device, but either ctrl_t5 does not parse them over MQTT or their meaning/value range is not verified. Change with caution - read-only rows are deliberately not sent.']),

                Toggle::make('configuration.settings.softMode')
                    ->label('Soft Mode')
                    ->helperText('Present in /dev_only_iot_device_info, but ctrl_t5 has no property parser for it.')
                    ->disabled()
                    ->dehydrated(false),

                Select::make('configuration.settings.stopTime')
                    ->label('Stop Time')
                    ->helperText('Read-only state value; not a property/set key.')
                    ->options([
                        '0' => 'Off',
                        '30' => '30 seconds',
                        '60' => '1 Minute',
                        '300' => '5 Minutes',
                        '600' => '10 Minutes',
                        '900' => '15 Minutes',
                        '1800' => '30 Minutes',
                        '3600' => '1 Hour',
                    ])
                    ->disabled()
                    ->dehydrated(false),

                Toggle::make('configuration.settings.deodor_tip_en')
                    ->label('Deodorant Reminder')
                    ->helperText('Read-only reminder flag; not a property/set key.')
                    ->disabled()
                    ->dehydrated(false),

                Textarea::make('configuration.settings.soundList')
                    ->label('Sound List')
                    ->helperText('Raw soundList array accepted by ctrl_t5. Not exposed by the app as a simple list.')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) $state)
                    ->rows(4)
                    ->disabled()
                    ->dehydrated(false),

                KeyValue::make('configuration.settings.sandSetUseConfig')
                    ->label('Sand Set Use Config')
                    ->helperText('The meaning is currently unknown')
                    ->disabled()
                    ->dehydrated(false),

                KeyValue::make('configuration.settings.sandFullWeight')
                    ->label('SandFullWeight')
                    ->helperText('The meaning is currently unknown')
                    ->disabled()
                    ->dehydrated(false),
            ]),
        ];
    }
}
