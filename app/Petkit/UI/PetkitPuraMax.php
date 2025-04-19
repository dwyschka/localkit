<?php

namespace App\Petkit\UI;

use App\Jobs\ServiceEnd;
use App\Jobs\ServiceStart;
use App\Models\Device;
use App\MQTT\GenericReply;
use App\MQTT\OtaMessage;
use App\MQTT\UserGet;
use App\Petkit\DeviceActions;
use App\Petkit\DeviceDefinition;
use App\Petkit\DeviceStates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Filament\Forms;
use Filament\Forms\Form;
class PetkitPuraMax
{

    public function formFields(): array {
        return [
            Forms\Components\Section::make('consumables')->columns(3)->schema([
                Forms\Components\TextInput::make('configuration.consumables.n50_durability')->numeric(),
                Forms\Components\TextInput::make('configuration.consumables.n50_next_change')->label('Next Reset in Days')->formatStateUsing(function ($state) {
                    if($state <= 0) {
                        return 'Not set';
                    }
                    $date = Carbon::parse($state);
                    $now = Carbon::now();

                    return round($now->diffInDays($date));

                })->readOnly()->disabled(true),
            ]),
            Forms\Components\Section::make('Litter')->columns(3)->schema([
                Forms\Components\TextInput::make('configuration.litter.usedTimes')->readOnly(),
                Forms\Components\TextInput::make('configuration.litter.percent')->readOnly(),
               Forms\Components\TextInput::make('configuration.litter.weight')->readOnly(),
            ]),
            Forms\Components\Section::make('Section')->schema([
                Forms\Components\Select::make('configuration.settings.language')->options([
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
                Forms\Components\Select::make('configuration.settings.unit')->options([
                    '0' => 'Pound',
                    '1' => 'Kg'
                ]),
                Forms\Components\Select::make('configuration.settings.sandType')->options([
                    '1' => 'Betonit/Mineral',
                    '2' => 'Tofu',
                    '3' => 'Sand'
                ]),
                Forms\Components\Select::make('configuration.settings.stillTime')->options([
                    '0' => 'Off',
                    '30' => '30 seconds',
                    '60' => '1 Minute',
                    '300' => '5 Minutes',
                    '600' => '10 Minutes',
                    '900' => '15 Minutes',
                    '1800' => '30 Minutes',
                    '3600' => '1 Hour',
                ]),
                Forms\Components\Select::make('configuration.settings.stopTime')->options([
                    '0' => 'Off',
                    '30' => '30 seconds',
                    '60' => '1 Minute',
                    '300' => '5 Minutes',
                    '600' => '10 Minutes',
                    '900' => '15 Minutes',
                    '1800' => '30 Minutes',
                    '3600' => '1 Hour',
                ]),
                Forms\Components\Select::make('configuration.settings.fixedTimeClear')->options([
                    '0' => 'Off',
                    '30' => '30 seconds',
                    '60' => '1 Minute',
                    '300' => '5 Minutes',
                    '600' => '10 Minutes',
                    '900' => '15 Minutes',
                    '1800' => '30 Minutes',
                    '3600' => '1 Hour',
                ]),
                Forms\Components\Select::make('configuration.settings.autoIntervalMin')->options([
                    '0' => 'Off',
                    '30' => '30 seconds',
                    '60' => '1 Minute',
                    '300' => '5 Minutes',
                    '600' => '10 Minutes',
                    '900' => '15 Minutes',
                    '1800' => '30 Minutes',
                    '3600' => '1 Hour',
                ]),
                Forms\Components\TextInput::make('configuration.settings.petInTipLimit')->readOnly(),
                Forms\Components\TextInput::make('configuration.settings.lightest')->readOnly(),
            ]),

                Forms\Components\Section::make('Options')->columns(3)->schema([
                    Forms\Components\Toggle::make('configuration.settings.kitten')->label('Kitten Mode'),
                    Forms\Components\Toggle::make('configuration.settings.autoWork')->label('Auto Work'),
                    Forms\Components\Toggle::make('configuration.settings.avoidRepeat')->label('Avoid Repeat'),
                    Forms\Components\Toggle::make('configuration.settings.underweight')->label('Underweight'),

                    Forms\Components\Toggle::make('configuration.settings.lightMode')->label('Display'),
                    Forms\Components\Toggle::make('configuration.settings.manualLock')->label('Child Lock'),
                    Forms\Components\Toggle::make('configuration.settings.downpos')->label('Continous Rotation'),
                    Forms\Components\Toggle::make('configuration.settings.deepClean')->label('Deep Clean'),
                    Forms\Components\Toggle::make('configuration.settings.bury')->label('Garbage Cover'),
                    Forms\Components\Toggle::make('configuration.settings.removeSand')->label('Remove Sand'),
                    Forms\Components\Toggle::make('configuration.settings.clickOkEnable')->label('Click OK Enable'),
                    Forms\Components\Toggle::make('configuration.settings.disturbMode')->label('Do not Disturb'),
                    Forms\Components\Toggle::make('configuration.settings.relateK3Switch')->label('Relate K3Switch')->disabled(),
                ]),



                Forms\Components\Section::make('Unknown')->schema([
                    Forms\Components\TextInput::make('configuration.settings.lightRange')->label('LightRange'),
                    Forms\Components\TextInput::make('configuration.settings.sandFullWeight')->label('SandFullWeight'),
                    Forms\Components\TextInput::make('configuration.settings.disturbRange')->label('disturbRange'),
                    Forms\Components\Repeater::make('configuration.settings.sandSetUseConfig')->schema([
                        Forms\Components\TextInput::make('')->label('Sand Set use Config'),
                    ])->disabled(),
                    Forms\Components\Toggle::make('configuration.settings.shareOpen')->label('Share Open')->disabled(),

                ])

        ];
    }
}
