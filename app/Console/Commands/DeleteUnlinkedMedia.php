<?php

namespace App\Console\Commands;

use App\Filament\Pages\MediaPage;
use App\Models\History;
use App\Models\MediaFile;
use App\Petkit\Storage\VideoRemuxer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes MediaFile rows (and their storage objects) whose event_id doesn't
 * match any History row's messageId - the pet_detect/drink_start/eat_start
 * MQTT event either never arrived, or arrived before that topic's
 * History::create() was deployed (see PetkitYumshareDual/PetkitEversweetUltra).
 */
class DeleteUnlinkedMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:media-delete-unlinked {--dry-run : List what would be deleted without deleting anything} {--force : Skip the confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete recordings that never got linked to an Activity';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $linkedEventIds = History::whereNotNull('messageId')->pluck('messageId');

        $unlinked = MediaFile::whereNotNull('event_id')
            ->whereNotIn('event_id', $linkedEventIds)
            ->get();

        if ($unlinked->isEmpty()) {
            $this->info('Nothing to delete.');

            return self::SUCCESS;
        }

        $this->table(
            ['file_id', 'event_id', 'module_type', 'size'],
            $unlinked->map(fn (MediaFile $media) => [
                $media->file_id,
                $media->event_id,
                $media->module_type,
                $media->size,
            ]),
        );

        if ($this->option('dry-run')) {
            $this->info(sprintf('%d unlinked recording(s) - dry run, nothing deleted.', $unlinked->count()));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf('Delete %d unlinked recording(s)? This cannot be undone.', $unlinked->count()))) {
            return self::SUCCESS;
        }

        $disk = Storage::disk(MediaPage::DISK);

        foreach ($unlinked as $media) {
            $disk->delete($media->object_key);
            $disk->delete(VideoRemuxer::mp4Key($media->object_key));
            $media->delete();
        }

        $this->info(sprintf('Deleted %d unlinked recording(s).', $unlinked->count()));

        return self::SUCCESS;
    }
}
