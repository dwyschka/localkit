<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Collection;

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
     * Truncates the selected log file to empty, keeping the file itself
     * (so anything still writing to it, e.g. debug_mode, doesn't need to
     * reopen a new handle).
     */
    public function clear(): void
    {
        $path = $this->availableFiles()->get($this->logFile);

        if ($path && is_writable($path)) {
            file_put_contents($path, '');
        }
    }

    /**
     * Deletes the selected log file entirely and selects the next one.
     */
    public function delete(): void
    {
        $path = $this->availableFiles()->get($this->logFile);

        if ($path && is_file($path)) {
            unlink($path);
        }

        $this->logFile = $this->availableFiles()->keys()->first() ?? '';
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
