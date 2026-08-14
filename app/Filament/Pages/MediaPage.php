<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaPage extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-photo';

    protected string $view = 'filament.pages.media-page';

    protected static ?string $slug = 'media';

    protected static string | \UnitEnum | null $navigationGroup = 'System';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Media';

    /** The object storage disk this page browses (see MediaDownloadController). */
    public const DISK = 'localkit_storage';

    /** Directory currently being browsed, relative to the disk root. */
    public string $path = '';

    /** Set when the disk couldn't be reached, instead of throwing. */
    public ?string $error = null;

    public function open(string $directory): void
    {
        $this->path = $directory;
    }

    public function up(): void
    {
        $this->path = collect(explode('/', $this->path))
            ->filter()
            ->slice(0, -1)
            ->implode('/');
    }

    /**
     * Breadcrumb segments as [label, path] pairs, root first.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function breadcrumbs(): array
    {
        $segments = collect(explode('/', $this->path))->filter()->values();

        $crumbs = [['/', '']];
        $built = '';

        foreach ($segments as $segment) {
            $built = $built === '' ? $segment : $built . '/' . $segment;
            $crumbs[] = [$segment, $built];
        }

        return $crumbs;
    }

    /**
     * Sub-directories of the current path. Empty (with $error set) if the
     * disk can't be reached, rather than throwing and blanking the page.
     *
     * @return Collection<int, string>
     */
    public function directories(): Collection
    {
        try {
            $directories = collect(Storage::disk(self::DISK)->directories($this->path))
                ->sort()
                ->values();

            $this->error = null;

            return $directories;
        } catch (Throwable $e) {
            $this->error = 'Storage backend unavailable: ' . $e->getMessage();

            return collect();
        }
    }

    /**
     * Files in the current path with size/modified metadata. Empty (with
     * $error set) if the disk can't be reached.
     *
     * @return Collection<int, array{path: string, name: string, size: int, modified: int}>
     */
    public function files(): Collection
    {
        $storage = Storage::disk(self::DISK);

        try {
            $files = collect($storage->files($this->path))
                ->map(fn (string $path) => [
                    'path' => $path,
                    'name' => basename($path),
                    'size' => $storage->size($path),
                    'modified' => $storage->lastModified($path),
                ])
                ->sortByDesc('modified')
                ->values();

            return $files;
        } catch (Throwable $e) {
            $this->error = 'Storage backend unavailable: ' . $e->getMessage();

            return collect();
        }
    }

    public function downloadUrl(string $path): string
    {
        return route('media.download', ['path' => $path]);
    }

    public function delete(string $path): void
    {
        try {
            Storage::disk(self::DISK)->delete($path);
        } catch (Throwable $e) {
            $this->error = 'Storage backend unavailable: ' . $e->getMessage();
        }
    }

    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $bytes < 10 ? 1 : 0) . ' ' . $units[$i];
    }
}
