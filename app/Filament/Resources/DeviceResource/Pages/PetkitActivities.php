<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\History;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

class PetkitActivities extends Page
{
    use InteractsWithRecord;
    use WithPagination;

    protected static string $resource = DeviceResource::class;

    protected string $view = 'filament.resources.device-resource.pages.petkit-activities';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Activities — ' . ($this->record->name ?? $this->record->serial_number);
    }

    /**
     * @return LengthAwarePaginator<int, History>
     */
    public function getHistories(): LengthAwarePaginator
    {
        return $this->record->histories()->with('media')->latest()->paginate(10);
    }

    /**
     * Heroicon + colour for a given history type.
     *
     * @return array{icon: string, color: string}
     */
    public static function typeMeta(?string $type): array
    {
        return match ($type) {
            'IN_USE' => ['icon' => 'heroicon-m-exclamation-triangle', 'color' => 'warning'],
            'CLEANING' => ['icon' => 'heroicon-m-arrow-path-rounded-square', 'color' => 'info'],
            'MAINTENANCE' => ['icon' => 'heroicon-m-wrench-screwdriver', 'color' => 'info'],
            'ERROR' => ['icon' => 'heroicon-m-exclamation-circle', 'color' => 'danger'],
            'EAT' => ['icon' => 'heroicon-m-cake', 'color' => 'success'],
            'DRINK' => ['icon' => 'heroicon-m-beaker', 'color' => 'info'],
            'DETECT' => ['icon' => 'heroicon-m-camera', 'color' => 'primary'],
            default => ['icon' => 'heroicon-m-bolt', 'color' => 'gray'],
        };
    }
}
