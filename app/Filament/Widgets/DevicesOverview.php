<?php

namespace App\Filament\Widgets;

use App\Models\Device;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class DevicesOverview extends BaseWidget
{
    protected static ?int $sort = -2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Your devices';

    public function table(Table $table): Table
    {
        return $table
            ->query(Device::query()->orderByDesc('last_heartbeat')->limit(6))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight(FontWeight::Bold)
                    ->icon(fn ($record): string => $record->device_type === 't4' ? 'heroicon-o-archive-box' : 'heroicon-o-inbox-stack')
                    ->iconColor('primary')
                    ->grow(),
                Tables\Columns\TextColumn::make('mqtt_connected')
                    ->badge()->alignEnd()
                    ->formatStateUsing(fn (string $state): string => $state === '0' ? 'Disconnected' : 'Connected')
                    ->color(fn (string $state): string => $state === '0' ? 'danger' : 'success'),
            ])
            ->recordUrl(fn (Device $record): string => \App\Filament\Resources\DeviceResource::getUrl('edit', ['record' => $record]));
    }
}
