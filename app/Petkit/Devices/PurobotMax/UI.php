<?php

namespace App\Petkit\Devices\PurobotMax;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\KeyValue;
use Illuminate\Support\Carbon;
use App\Filament\Forms\Components\MinutesTimePicker;

/**
 * Sections below mirror the PetKit app's own settings screens for this
 * device (T6SettingActivity, shared with T5/T6/"t6" deviceType=21,27), not
 * an arbitrary grouping - see the section-by-section notes for which app
 * screen each one comes from.
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
                        $date = Carbon::parse($state);
                        $now = Carbon::now();

                        return round($now->diffInDays($date));
                    })
                    ->readOnly()
                    ->disabled(true),
            ]),

            // App screen "Set_clean" (T6SmartSettingActivity) - bundles
            // cleaning, the N50 spray module, the toilet light and the
            // cleaning do-not-disturb period onto one screen in the app.
            Section::make('Set Clean')->schema([
                Toggle::make('configuration.settings.autoWork')
                    ->helperText('After the pet leaves, the device will start automatic cleaning')
                    ->label('Auto Cleaning'),

                Select::make('configuration.settings.autoIntervalMin')
                    ->label('Time Interval Of Each Cleaning')
                    ->helperText('During the set time, the device will not clean repeatedly after the pet used it multiple times')
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

                Select::make('configuration.settings.stillTime')
                    ->label('Delayed Cleaning')
                    ->helperText('Time interval before the device starts cleaning')
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
                    ->helperText('Disable auto cleaning when the pet is lighter than the minimum weight')
                    ->label('Disable auto cleaning for light weight'),

                TextInput::make('configuration.settings.lightest')
                    ->label('Minimum Weight'),

                Toggle::make('configuration.settings.kitten')
                    ->helperText('Disable auto and periodical cleaning')
                    ->label('Kitten Protection'),

                Toggle::make('configuration.settings.deepClean')
                    ->helperText('Additional/longer cleaning pass')
                    ->label('Deep Cleaning'),

                Toggle::make('configuration.settings.bury')
                    ->helperText('Cover waste with litter before the cleaning cycle')
                    ->label('Waste Covering'),

                Toggle::make('configuration.settings.tumbling')
                    ->label('Tumbling'),

                Toggle::make('configuration.settings.sandSaving')
                    ->helperText('Turn on when clumps are relatively large')
                    ->label('Litter Saving'),

                Toggle::make('configuration.settings.fixedTimeClear')
                    ->label('Scheduled Cleaning')
                    ->helperText('Enables the clean schedule configured in the PetKit app - the schedule itself is managed through PetKit\'s cloud API, not here')
                    ->disabled(true),

                Toggle::make('configuration.settings.toiletLight')
                    ->label('Toilet Light')
                    ->helperText('Turn on the light while the toilet is in use - see "Light Setting" below for the active-time schedule'),

                Toggle::make('configuration.settings.autoSpray')
                    ->label('Auto Spray')
                    ->helperText('Automatically spray after cleaning'),

                Toggle::make('configuration.settings.deepSpray')
                    ->label('Deep Spray')
                    ->helperText('More intensive/longer spraying'),

                Toggle::make('configuration.settings.fixedTimeSpray')
                    ->label('Scheduled Spray')
                    ->helperText('Enables the spray schedule configured in the PetKit app - the schedule itself is managed through PetKit\'s cloud API, not here')
                    ->disabled(true),

                Toggle::make('configuration.settings.disturbMode')
                    ->label('Do Not Disturb (Cleaning)')
                    ->helperText('Stop cleaning during the period below'),

                Repeater::make('configuration.settings.distrubMultiRange.ranges')
                    ->columns(2)
                    ->label('Do Not Disturb Period')
                    ->reorderableWithButtons()
                    ->schema([
                        MinutesTimePicker::make('from'),
                        MinutesTimePicker::make('till'),
                    ]),
            ]),

            // App screen "Choose_light_screen_title" (T6LightSettingActivity).
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

            // App screen "Intelligent_detection" (T6IntelligentDetectionActivity)
            // - smartFrame/petDetection (camera-based) are deliberately not
            // modeled here, see Configuration.php's class doc comment.
            Section::make('Intelligent Detection')->schema([
                Toggle::make('configuration.settings.toiletDetection')
                    ->label('Toilet Use Detection'),
            ]),

            // App screen "AiPhActivity" - urine pH health monitoring.
            Section::make('AI pH')->schema([
                Toggle::make('configuration.settings.phDetection')
                    ->label('pH Detection')
                    ->helperText('Health feature based on the urine pH sensor'),

                Toggle::make('configuration.settings.lightAssist')
                    ->label('Light Assist for Cleaning'),
            ]),

            // App screen "Speech_settings" (T6SpeechSettingsActivity) +
            // its "Choose_do_not_disturb_title" sub-screen
            // (T6SpeechDoNotDisturbSettingActivity). volume/selectedSound are
            // not exposed - the app picks them from a server-provided
            // discrete list rather than a plain numeric range (see
            // Configuration.php).
            Section::make('Speech Settings')->schema([
                Toggle::make('configuration.settings.toneMode')
                    ->label('Voice Prompts'),

                Toggle::make('configuration.settings.systemSoundEnable')
                    ->label('System Voice Announcements'),

                Repeater::make('configuration.settings.toneMultiRange.ranges')
                    ->columns(2)
                    ->label('Voice Prompts Do Not Disturb Period')
                    ->reorderableWithButtons()
                    ->schema([
                        MinutesTimePicker::make('from'),
                        MinutesTimePicker::make('till'),
                    ]),
            ]),

            // App screen "Feeder_setting_manual" (KidLockActivity).
            Section::make('Child Lock')->schema([
                Toggle::make('configuration.settings.manualLock')
                    ->helperText('After activating the child lock, the buttons on the device are not available during standby')
                    ->label('Child Lock'),

                Toggle::make('configuration.settings.clickOkEnable')
                    ->label('OK Button'),
            ]),

            // App screen T6SettingActivity's share row (T6SettingShareActivity).
            Section::make('Share')->schema([
                Toggle::make('configuration.settings.shareOpen')
                    ->label('Share Open'),
            ]),

            // Confirmed real and simple (T6SettingActivity's main screen
            // checkboxes/pickers), but not grouped under any sub-screen of
            // their own in the app.
            Section::make('Device')->schema([
                Toggle::make('configuration.settings.lightMode')
                    ->label('Screen Light'),

                Select::make('configuration.settings.unit')
                    ->label('Weight Unit')
                    ->options([
                        '0' => 'Kg',
                        '1' => 'Pound',
                    ]),

                Select::make('configuration.settings.sandType')
                    ->label('Litter Type')
                    ->helperText('Resolved from OverseaToiletLitterSettingFragment: the app is 1-based, 0 = not set yet')
                    ->options([
                        '0' => 'Not set',
                        '1' => 'Bentonite / Ore',
                        '2' => 'Tofu',
                        '3' => 'Mixed',
                    ]),
            ]),

            Section::make('Unknown / Unverified')->schema([
                ViewField::make('warning')
                    ->columnSpanFull()
                    ->view('filament.forms.warning')
                    ->viewData(['message' => 'These fields exist on the device but no app screen was found that sets them (or, for the sound fields, the app resolves them through a server-provided list rather than a plain range) - meaning/value range not verified. Change with caution.']),

                Toggle::make('configuration.settings.downpos')
                    ->helperText('Target rest position of the cleaning mechanism - exact meaning unverified'),

                Toggle::make('configuration.settings.softMode')
                    ->helperText('No app screen reference found for this field'),

                Toggle::make('configuration.settings.deodor_tip_en')
                    ->label('Deodorant Tip Notification')
                    ->helperText('No app screen reference found for this field'),

                Toggle::make('configuration.settings.soundEnable')
                    ->helperText('No app screen reference found for this field'),

                TextInput::make('configuration.settings.volume')
                    ->helperText('Raw device value - the app picks this from a server-provided list, not a plain range')
                    ->numeric(),

                TextInput::make('configuration.settings.selectedSound')
                    ->helperText('Raw device value - the app picks this from a server-provided list, not a plain range')
                    ->numeric(),

                TextInput::make('configuration.settings.voice')
                    ->helperText('Ties into the AI voice message feature (AiVoiceActivity), not a simple on/off switch')
                    ->numeric(),

                KeyValue::make('configuration.settings.schedule')
                    ->disabled(true)
                    ->helperText('Scheduled clean/spray times - managed through PetKit\'s cloud API, shown here read-only so a save does not wipe it'),

                KeyValue::make('configuration.settings.sandFullWeight')
                    ->helperText('Device-internal, not exposed by the vendor app either'),

                KeyValue::make('configuration.settings.sandSetUseConfig')
                    ->disabled(true)
                    ->helperText('Device-internal, not exposed by the vendor app either'),
            ]),
        ];
    }
}
