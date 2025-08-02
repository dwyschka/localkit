<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers;
use App\Jobs\ServiceStart;
use App\Models\Device;
use App\Petkit\DeviceActions;
use App\Petkit\Devices\PetkitFreshElementSolo;
use App\Petkit\Devices\PetkitPuraMax;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;


class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->columnSpan('full'),
                Forms\Components\TextInput::make('firmware')->columnSpan('half')->readOnly(),
                Forms\Components\TextInput::make('mac')->columnSpan('half')->readOnly(),
                Forms\Components\Select::make('device_type')->options([
                    't4' => PetkitPuraMax::deviceName(),
                    'd4' => PetkitFreshElementSolo::deviceName()
                ])
                    ->columnSpan('half')->disabled(),
                Forms\Components\TextInput::make('secret')->columnSpan('half')->readOnly(),
                Forms\Components\TextInput::make('petkit_id')->columnSpan('half')->readOnly(),
                Forms\Components\TextInput::make('mqtt_subdomain')->columnSpan('full'),
                Forms\Components\Checkbox::make('ota_state')->columnSpan('full'),

                Forms\Components\Fieldset::make('Device Configuration')->schema(
                    $form->getModelInstance()->ui()->formFields()
                )

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('1s')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('working_state')->badge(),
                Tables\Columns\TextColumn::make('mqtt_connected')
                    ->badge()
                    ->formatStateUsing(function (string $state) {
                        return $state === '0' ? 'Disconnected' : 'Connected';
                    })
                    ->color(fn(string $state): string => match ($state) {
                        '0' => 'danger',
                        '1' => 'success'
                    }),
                Tables\Columns\TextColumn::make('error')
                    ->badge()
                    ->formatStateUsing(function (string $state) {
                        return __('petkit.error.'.$state);
                    })
                    ->color(fn(string $state): string => 'danger'),
                Tables\Columns\TextColumn::make('serial_number'),
                Tables\Columns\TextColumn::make('last_heartbeat')->dateTime('Y-m-d H:i:s'),
                Tables\Columns\ToggleColumn::make('proxy_mode')
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_activities')
                    ->label('Activities')
                    ->icon('heroicon-m-bolt')
                    ->color('purple')
                    ->url(fn($record) => DeviceResource::getUrl('activities', ['record' => $record])),

                Tables\Actions\ActionGroup::make(
                    DeviceActions::actions()
                )
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
            'activities' => Pages\PetkitActivities::route('/order/{record}/activities'),

        ];
    }
}
