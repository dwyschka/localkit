<?php

namespace App\Filament\Resources\PetResource\RelationManagers;

use App\Filament\Resources\DeviceResource\Pages\PetkitActivities;
use App\Models\History;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only Activity Log for a single pet - every History row this pet was
 * matched to via the async pet_discern event (see YumshareDual/EversweetUltra
 * Device::mergeHistory()), across every device that recognized it.
 */
class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'Activity Log';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('device.name')
                    ->label('Device')
                    ->default('—'),

                TextColumn::make('type')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (History $record): string => $record->title())
                    ->color(fn (History $record): string => PetkitActivities::typeMeta($record->type)['color']),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->state(fn (History $record): string => $record->eventDuration() . 's'),

                TextColumn::make('created_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
