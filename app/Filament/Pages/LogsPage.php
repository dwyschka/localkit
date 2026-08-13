<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogsPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.logs-page';

    protected static ?string $slug = 'logs';

    protected static string | \UnitEnum | null $navigationGroup = 'System';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Logs';

    /** Currently selected log file (basename). */
    public string $logFile = '';

    /** How many trailing lines to display. */
    public int $lines = 300;

    public function mount(): void
    {
        $this->logFile = $this->availableFiles()->keys()->first() ?? '';
    }

    /**
     * Log files in storage/logs keyed by basename, most recently modified first.
     *
     * @return Collection<string, string>
     */
    public function availableFiles(): Collection
    {
        $dir = storage_path('logs');

        if (! is_dir($dir)) {
            return collect();
        }

        return collect(glob($dir . '/*.log'))
            ->sortByDesc(fn (string $path) => filemtime($path))
            ->mapWithKeys(fn (string $path) => [basename($path) => $path]);
    }

    /**
     * The trailing lines of the selected log file, newest first.
     */
    public function getLogContent(): string
    {
        $path = $this->availableFiles()->get($this->logFile);

        if (! $path || ! is_readable($path)) {
            return '';
        }

        // Read only the tail to stay cheap on large logs.
        $content = $this->tail($path, $this->lines);

        return trim($content) === '' ? '' : $content;
    }

    /**
     * Streams the full, selected log file to the browser as a download -
     * unlike getLogContent(), this isn't limited to the trailing $lines.
     */
    public function download(): ?BinaryFileResponse
    {
        $path = $this->availableFiles()->get($this->logFile);

        if (! $path || ! is_readable($path)) {
            return null;
        }

        return response()->download($path, $this->logFile);
    }

    private function tail(string $path, int $lines): string
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();

        $start = max(0, $lastLine - $lines);
        $out = [];

        $file->seek($start);
        while (! $file->eof()) {
            $out[] = $file->fgets();
        }

        // newest first
        return implode('', array_reverse($out));
    }
}
