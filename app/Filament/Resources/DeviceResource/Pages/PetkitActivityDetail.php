<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\History;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Log;
use Throwable;

class PetkitActivityDetail extends Page
{
    use InteractsWithRecord;

    protected static string $resource = DeviceResource::class;

    protected string $view = 'filament.resources.device-resource.pages.petkit-activity-detail';

    public History $history;

    public function mount(int|string $record, int|string $history): void
    {
        Log::info('PetkitActivityDetail mount', ['record' => $record, 'history' => $history]);

        try {
            $this->record = $this->resolveRecord($record);
        } catch (Throwable $e) {
            Log::error('PetkitActivityDetail: resolveRecord failed', [
                'record' => $record,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        try {
            $this->history = History::with('media')
                ->where('device_id', $this->record->id)
                ->findOrFail($history);
        } catch (Throwable $e) {
            Log::error('PetkitActivityDetail: History lookup failed', [
                'record' => $record,
                'resolved_device_id' => $this->record->id,
                'history' => $history,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
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
