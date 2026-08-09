<?php

namespace App\Console\Commands;

use App\Models\History;
use App\Models\Pet;
use Illuminate\Console\Command;

class BackfillHistoryPets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'history:backfill-pets {--dry-run : Show what would change without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign pet_id to IN_USE history entries that were never matched (pet_weight in grams -> nearest pet in kg)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = History::query()
            ->where('type', 'IN_USE')
            ->whereNull('pet_id');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No unmatched IN_USE history entries found.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%s %d unmatched IN_USE entries...', $dryRun ? '[dry-run] Checking' : 'Backfilling', $total));

        $matched = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(200, function ($entries) use (&$matched, &$skipped, $dryRun) {
            foreach ($entries as $entry) {
                $grams = $entry->parameters['pet_weight'] ?? null;

                if ($grams === null) {
                    $skipped++;
                    continue;
                }

                $pet = Pet::nearestWeight($grams / 1000);

                if (! $pet) {
                    $skipped++;
                    continue;
                }

                $this->line(sprintf('  #%d  %dg -> %s (%skg)', $entry->id, $grams, $pet->name, $pet->weight));

                if (! $dryRun) {
                    $entry->update(['pet_id' => $pet->id]);
                }

                $matched++;
            }
        });

        $this->newLine();
        $this->info(sprintf('%sMatched: %d, skipped (no pet in range): %d', $dryRun ? '[dry-run] ' : '', $matched, $skipped));

        return self::SUCCESS;
    }
}
