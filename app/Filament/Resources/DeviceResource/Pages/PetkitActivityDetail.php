<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\History;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class PetkitActivityDetail extends Page
{
    use InteractsWithRecord;

    protected static string $resource = DeviceResource::class;

    protected string $view = 'filament.resources.device-resource.pages.petkit-activity-detail';

    public History $history;

    public function mount(int|string $record, int|string $history): void
    {
        $this->record = $this->resolveRecord($record);

        $this->history = History::with('media')
            ->where('device_id', $this->record->id)
            ->findOrFail($history);
    }

    public function getTitle(): string
    {
        return $this->history->title();
    }

    public function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $bytes < 10 ? 1 : 0) . ' ' . $units[$i];
    }
}
