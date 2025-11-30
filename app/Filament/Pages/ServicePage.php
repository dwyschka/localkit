<?php

namespace App\Filament\Pages;

use App\Management\Supervisor;

use App\Models\Service;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ServicePage extends Page implements HasTable
{
    Use InteractsWithTable;
    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';

    protected static string $view = 'filament.pages.service-page';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Services';

    public array $services = [];

    public function mount()
    {
        $services = app(Supervisor::class)->allServices();
        $this->services = [
            'services' => $services->map(function ($service) {
                return [
                    ...$service,
                    'readonly' => in_array($service['name'], ['php-fpm', 'nginx', 'init'])
                ];
            })->sortBy('readonly')->toArray()
        ];
    }


    public function table(Table $table): Table
    {

        return $table
            ->query(Service::query())
            ->columns([
                TextColumn::make('name')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('statename')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'RUNNING' => 'success',
                        'EXITED' => 'danger',
                        'STOPPED' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

            ])
            ->actions([
                Action::make('stop')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn(Service $record) => $record['statename'] === 'RUNNING' && !$record['readonly'])
                    ->action(function (Service $record) {
                        app(Supervisor::class)->stop($record['name']);

                        Notification::make()
                            ->success()
                            ->title('Service stopped')
                            ->body("{$record['name']} has been stopped")
                            ->send();
                    }),

                Action::make('start')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn(Service $record) => $record['statename'] !== 'RUNNING' && !$record['readonly'])
                    ->action(function (Service $record) {
                        app(Supervisor::class)->start($record['name']);

                        Notification::make()
                            ->success()
                            ->title('Service started')
                            ->send();
                    }),

                Action::make('restart')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn(Service $record) => $record['statename'] === 'RUNNING' && !$record['readonly'])
                    ->action(function (Service $record) {
                        app(Supervisor::class)->restart($record['name']);

                        Notification::make()
                            ->success()
                            ->title('Service restarted')
                            ->send();
                    }),
            ])
            ->poll('5s') // Auto-refresh every 5 seconds
            ->paginated(false);

    }


}
