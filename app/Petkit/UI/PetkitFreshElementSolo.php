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
class PetkitFreshElementSolo
{

    public function formFields(): array {
        return [
            Forms\Components\Section::make('Section')->schema([
                Forms\Components\TextInput::make('configuration.settings.factor')->numeric(),
                Forms\Components\TextInput::make('configuration.settings.amount')->numeric(),

            ]),

                Forms\Components\Section::make('Options')->columns(3)->schema([
                    Forms\Components\Toggle::make('configuration.settings.feedSound')->label('Feeding chime'),
                    Forms\Components\Toggle::make('configuration.settings.manualLock')->label('Child Lock'),
                    Forms\Components\Toggle::make('configuration.settings.foodWarn')->label('Refill alarm'),
                ]),



                Forms\Components\Section::make('Unknown')->schema([
                    Forms\Components\TextInput::make('configuration.settings.foodWarnRange')->label('FoodWarnRange'),
                    Forms\Components\TextInput::make('configuration.settings.lightRange')->label('LightRange'),
                    Forms\Components\Toggle::make('configuration.settings.shareOpen')->label('Share Open'),
                    Forms\Components\Toggle::make('configuration.settings.multiConfig')->label('Multi Config'),
                ]),


        ];
    }
}
