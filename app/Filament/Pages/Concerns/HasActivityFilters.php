<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Device;
use App\Models\History;
use App\Models\Pet;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

/**
 * Trait providing shared activity filtering logic, query scoping, URL parameter binding,
 * and filter management across Filament activity pages (ActivitiesPage and TimelinePage).
 */
trait HasActivityFilters
{
    // URL Query Parameter Keys
    public const QUERY_PARAM_DEVICES = 'devices';
    public const QUERY_PARAM_PETS = 'pets';
    public const QUERY_PARAM_TYPES = 'types';
    public const QUERY_PARAM_MEDIA = 'media';
    public const QUERY_PARAM_DATE_FROM = 'date_from';
    public const QUERY_PARAM_DATE_TO = 'date_to';
    public const QUERY_PARAM_TIME_FROM = 'time_from';
    public const QUERY_PARAM_TIME_TO = 'time_to';
    public const QUERY_PARAM_MAX_RESULTS = 'max_results';

    // Quick Date Presets
    public const PRESET_TODAY = 'today';
    public const PRESET_YESTERDAY = 'yesterday';
    public const PRESET_LAST_7_DAYS = 'last_7_days';
    public const PRESET_LAST_30_DAYS = 'last_30_days';

    // Quick Time of Day Presets
    public const PRESET_TIME_EARLY_MORNING = 'early_morning';
    public const PRESET_TIME_MORNING = 'morning';
    public const PRESET_TIME_AFTERNOON = 'afternoon';
    public const PRESET_TIME_EVENING = 'evening';
    public const PRESET_TIME_NIGHT = 'night';

    // Media Filter Types
    public const MEDIA_TYPE_VIDEO = 'video';
    public const MEDIA_TYPE_PHOTO = 'photo';
    public const MEDIA_TYPE_INFO = 'info';

    public const UNKNOWN_PET = 'unknown';

    public const DEFAULT_MAX_RESULTS = 25;
    public const MAX_RESULTS_OPTIONS = [25, 50, 100, 250, 500, 1000];

    /**
     * Filter: selected Device IDs (multi-select).
     *
     * @var array<int|string>
     */
    #[Url(as: self::QUERY_PARAM_DEVICES, except: [])]
    public array $deviceIds = [];

    /**
     * Livewire lifecycle hook: automatically called when component mounts.
     * Normalizes query parameter formats (arrays, scalars, camelCase, legacy keys).
     */
    public function mountHasActivityFilters(): void
    {
        $request = request();

        if (empty($this->deviceIds)) {
            $deviceParam = $request->query('deviceId') ?? $request->query('device_id') ?? $request->query('device');
            if (! empty($deviceParam)) {
                $this->deviceIds = is_array($deviceParam) ? $deviceParam : [$deviceParam];
            }
        }

        if (empty($this->petIds)) {
            $petParam = $request->query('petId') ?? $request->query('pet_id') ?? $request->query('pet');
            if (! empty($petParam)) {
                $this->petIds = is_array($petParam) ? $petParam : [$petParam];
            }
        }

        if (empty($this->types)) {
            $typeParam = $request->query('type');
            if (! empty($typeParam)) {
                $this->types = is_array($typeParam) ? $typeParam : [$typeParam];
            }
        }

        if (empty($this->mediaTypes)) {
            $mediaParam = $request->query('media') ?? $request->query('mediaType') ?? $request->query('media_type');
            if (! empty($mediaParam)) {
                $this->mediaTypes = is_array($mediaParam) ? $mediaParam : [$mediaParam];
            }
        }

        if (empty($this->dateFrom) && $request->has('dateFrom')) {
            $this->dateFrom = (string) $request->query('dateFrom');
        }
        if (empty($this->dateTo) && $request->has('dateTo')) {
            $this->dateTo = (string) $request->query('dateTo');
        }

        if (empty($this->timeFrom) && $request->has('timeFrom')) {
            $this->timeFrom = (string) $request->query('timeFrom');
        }
        if (empty($this->timeTo) && $request->has('timeTo')) {
            $this->timeTo = (string) $request->query('timeTo');
        }

        if ($request->has(self::QUERY_PARAM_MAX_RESULTS) || $request->has('maxResults')) {
            $maxParam = $request->query(self::QUERY_PARAM_MAX_RESULTS) ?? $request->query('maxResults');
            if ($maxParam === 'all' || $maxParam === '0' || $maxParam === '' || $maxParam === null) {
                $this->maxResults = 0;
            } elseif (is_numeric($maxParam) && (int) $maxParam > 0) {
                $this->maxResults = (int) $maxParam;
            }
        }
    }

    /**
     * Filter: selected Pet IDs (multi-select, can include UNKNOWN_PET).
     *
     * @var array<int|string>
     */
    #[Url(as: self::QUERY_PARAM_PETS, except: [])]
    public array $petIds = [];

    /**
     * Filter: selected activity/event types (multi-select, e.g. ['IN_USE', 'EAT']).
     *
     * @var array<string>
     */
    #[Url(as: self::QUERY_PARAM_TYPES, except: [])]
    public array $types = [];

    /**
     * Filter: selected media types (multi-select, e.g. ['photo', 'video', 'info']).
     *
     * @var array<string>
     */
    #[Url(as: self::QUERY_PARAM_MEDIA, except: [])]
    public array $mediaTypes = [];

    /**
     * Filter: start date for activity range (inclusive, formatted as Y-m-d or Unix timestamp).
     */
    #[Url(as: self::QUERY_PARAM_DATE_FROM, except: null)]
    public ?string $dateFrom = null;

    /**
     * Filter: end date for activity range (inclusive, formatted as Y-m-d or Unix timestamp).
     */
    #[Url(as: self::QUERY_PARAM_DATE_TO, except: null)]
    public ?string $dateTo = null;

    /**
     * Filter: start time of day (formatted as H:i, e.g. 08:00).
     */
    #[Url(as: self::QUERY_PARAM_TIME_FROM, except: null)]
    public ?string $timeFrom = null;

    /**
     * Filter: end time of day (formatted as H:i, e.g. 18:00).
     */
    #[Url(as: self::QUERY_PARAM_TIME_TO, except: null)]
    public ?string $timeTo = null;

    /**
     * Filter: maximum number of total activity events to process/display (0 means uncapped / ∞).
     */
    #[Url(as: self::QUERY_PARAM_MAX_RESULTS, except: self::DEFAULT_MAX_RESULTS)]
    public int $maxResults = self::DEFAULT_MAX_RESULTS;

    /**
     * Normalizes maxResults input so 0, empty, or negative values resolve to 0 (∞ / all).
     */
    public function updatedMaxResults(mixed $value): void
    {
        $this->maxResults = ($value === '' || $value === null || (int) $value <= 0)
            ? 0
            : (int) $value;

        $this->onFilterUpdated();
    }

    public function getCustomMaxResultsProperty(): ?int
    {
        return $this->maxResults > 0 ? $this->maxResults : null;
    }

    public function setCustomMaxResultsProperty(mixed $value): void
    {
        $this->updatedMaxResults($value);
    }

    /**
     * Hook called after any filter is modified (resets pagination if WithPagination is used).
     */
    protected function onFilterUpdated(): void
    {
        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /**
     * Clears all active filters and resets pagination.
     */
    public function resetFilters(): void
    {
        $this->reset('deviceIds', 'petIds', 'types', 'mediaTypes', 'dateFrom', 'dateTo', 'timeFrom', 'timeTo');
        $this->maxResults = self::DEFAULT_MAX_RESULTS;
        $this->onFilterUpdated();
    }

    /**
     * Sets a quick relative date preset using epoch timestamps.
     */
    public function setDatePreset(string $preset): void
    {
        $timezone = (string) config('app.timezone');
        $now = Carbon::now($timezone);

        [$this->dateFrom, $this->dateTo] = match ($preset) {
            self::PRESET_TODAY => [
                (string) $now->copy()->startOfDay()->timestamp,
                (string) $now->copy()->endOfDay()->timestamp,
            ],
            self::PRESET_YESTERDAY => [
                (string) $now->copy()->subDay()->startOfDay()->timestamp,
                (string) $now->copy()->subDay()->endOfDay()->timestamp,
            ],
            self::PRESET_LAST_7_DAYS => [
                (string) $now->copy()->subDays(6)->startOfDay()->timestamp,
                (string) $now->copy()->endOfDay()->timestamp,
            ],
            self::PRESET_LAST_30_DAYS => [
                (string) $now->copy()->subDays(29)->startOfDay()->timestamp,
                (string) $now->copy()->endOfDay()->timestamp,
            ],
            default => [null, null],
        };

        $this->onFilterUpdated();
    }

    /**
     * Sets a quick time-of-day preset window.
     */
    public function setTimePreset(string $preset): void
    {
        [$this->timeFrom, $this->timeTo] = match ($preset) {
            self::PRESET_TIME_EARLY_MORNING => ['05:00', '08:00'],
            self::PRESET_TIME_MORNING => ['08:00', '12:00'],
            self::PRESET_TIME_AFTERNOON => ['12:00', '17:00'],
            self::PRESET_TIME_EVENING => ['17:00', '21:00'],
            self::PRESET_TIME_NIGHT => ['21:00', '06:00'],
            default => [null, null],
        };

        $this->onFilterUpdated();
    }

    /**
     * Checks if a specific date preset is currently active.
     */
    public function isDatePresetActive(string $preset): bool
    {
        if (empty($this->dateFrom) || empty($this->dateTo)) {
            return false;
        }

        $timezone = (string) config('app.timezone');
        $now = Carbon::now($timezone);

        return match ($preset) {
            self::PRESET_TODAY => ($this->dateFrom === (string) $now->copy()->startOfDay()->timestamp && $this->dateTo === (string) $now->copy()->endOfDay()->timestamp)
                || ($this->dateFrom === $now->toDateString() && $this->dateTo === $now->toDateString()),
            self::PRESET_YESTERDAY => ($this->dateFrom === (string) $now->copy()->subDay()->startOfDay()->timestamp && $this->dateTo === (string) $now->copy()->subDay()->endOfDay()->timestamp)
                || ($this->dateFrom === $now->copy()->subDay()->toDateString() && $this->dateTo === $now->copy()->subDay()->toDateString()),
            self::PRESET_LAST_7_DAYS => ($this->dateFrom === (string) $now->copy()->subDays(6)->startOfDay()->timestamp && $this->dateTo === (string) $now->copy()->endOfDay()->timestamp)
                || ($this->dateFrom === $now->copy()->subDays(6)->toDateString() && $this->dateTo === $now->toDateString()),
            self::PRESET_LAST_30_DAYS => ($this->dateFrom === (string) $now->copy()->subDays(29)->startOfDay()->timestamp && $this->dateTo === (string) $now->copy()->endOfDay()->timestamp)
                || ($this->dateFrom === $now->copy()->subDays(29)->toDateString() && $this->dateTo === $now->toDateString()),
            default => false,
        };
    }

    /**
     * Checks if a specific time-of-day preset is currently active.
     */
    public function isTimePresetActive(string $preset): bool
    {
        return match ($preset) {
            self::PRESET_TIME_EARLY_MORNING => $this->timeFrom === '05:00' && $this->timeTo === '08:00',
            self::PRESET_TIME_MORNING => $this->timeFrom === '08:00' && $this->timeTo === '12:00',
            self::PRESET_TIME_AFTERNOON => $this->timeFrom === '12:00' && $this->timeTo === '17:00',
            self::PRESET_TIME_EVENING => $this->timeFrom === '17:00' && $this->timeTo === '21:00',
            self::PRESET_TIME_NIGHT => $this->timeFrom === '21:00' && $this->timeTo === '06:00',
            default => false,
        };
    }

    public function getCustomDateFromProperty(): ?string
    {
        if (empty($this->dateFrom)) {
            return null;
        }

        $timezone = (string) config('app.timezone');

        return is_numeric($this->dateFrom)
            ? Carbon::createFromTimestamp((int) $this->dateFrom, $timezone)->toDateString()
            : Carbon::parse($this->dateFrom, $timezone)->toDateString();
    }

    public function getCustomDateToProperty(): ?string
    {
        if (empty($this->dateTo)) {
            return null;
        }

        $timezone = (string) config('app.timezone');

        return is_numeric($this->dateTo)
            ? Carbon::createFromTimestamp((int) $this->dateTo, $timezone)->toDateString()
            : Carbon::parse($this->dateTo, $timezone)->toDateString();
    }

    public function setCustomDateFrom(?string $value): void
    {
        $this->dateFrom = ! empty($value) ? $value : null;
        $this->onFilterUpdated();
    }

    public function setCustomDateTo(?string $value): void
    {
        $this->dateTo = ! empty($value) ? $value : null;
        $this->onFilterUpdated();
    }

    public function setCustomDateFromProperty(?string $value): void
    {
        $this->setCustomDateFrom($value);
    }

    public function setCustomDateToProperty(?string $value): void
    {
        $this->setCustomDateTo($value);
    }

    public function setMaxResults(?int $limit): void
    {
        $this->maxResults = ($limit !== null && $limit > 0) ? $limit : 0;
        $this->onFilterUpdated();
    }

    public function removeDeviceFilter(string|int $id): void
    {
        $this->deviceIds = array_values(array_filter(
            $this->deviceIds,
            fn ($d): bool => (string) $d !== (string) $id
        ));
        $this->onFilterUpdated();
    }

    public function removePetFilter(string|int $id): void
    {
        $this->petIds = array_values(array_filter(
            $this->petIds,
            fn ($p): bool => (string) $p !== (string) $id
        ));
        $this->onFilterUpdated();
    }

    public function removeTypeFilter(string $type): void
    {
        $this->types = array_values(array_filter(
            $this->types,
            fn ($t): bool => $t !== $type
        ));
        $this->onFilterUpdated();
    }

    public function removeMediaFilter(string $type): void
    {
        $this->mediaTypes = array_values(array_filter(
            $this->mediaTypes,
            fn ($t): bool => $t !== $type
        ));
        $this->onFilterUpdated();
    }

    public function removeDateFilter(): void
    {
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->onFilterUpdated();
    }

    public function removeTimeFilter(): void
    {
        $this->timeFrom = null;
        $this->timeTo = null;
        $this->onFilterUpdated();
    }

    /**
     * Clears both the date range and time-of-day filters simultaneously.
     */
    public function removeDateTimeFilter(): void
    {
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->timeFrom = null;
        $this->timeTo = null;
        $this->onFilterUpdated();
    }

    /**
     * Checks if either a date range or a time-of-day filter is currently active.
     */
    public function hasActiveDateTimeFilter(): bool
    {
        return ! empty($this->dateFrom)
            || ! empty($this->dateTo)
            || ! empty($this->timeFrom)
            || ! empty($this->timeTo);
    }

    public function removeMaxResultsFilter(): void
    {
        $this->maxResults = self::DEFAULT_MAX_RESULTS;
        $this->onFilterUpdated();
    }

    public function hasActiveFilters(): bool
    {
        return ! empty($this->deviceIds)
            || ! empty($this->petIds)
            || ! empty($this->types)
            || ! empty($this->mediaTypes)
            || ! empty($this->dateFrom)
            || ! empty($this->dateTo)
            || ! empty($this->timeFrom)
            || ! empty($this->timeTo)
            || ($this->maxResults !== self::DEFAULT_MAX_RESULTS);
    }

    public function activeDateFilterLabel(): ?string
    {
        if (empty($this->dateFrom) && empty($this->dateTo)) {
            return null;
        }

        $timezone = (string) config('app.timezone');
        $now = Carbon::now($timezone);

        $todayStart = (string) $now->copy()->startOfDay()->timestamp;
        $todayEnd = (string) $now->copy()->endOfDay()->timestamp;
        $yesterdayStart = (string) $now->copy()->subDay()->startOfDay()->timestamp;
        $yesterdayEnd = (string) $now->copy()->subDay()->endOfDay()->timestamp;

        if (($this->dateFrom === $todayStart && $this->dateTo === $todayEnd) || ($this->dateFrom === $now->toDateString() && $this->dateTo === $now->toDateString())) {
            return __('Today');
        }

        if (($this->dateFrom === $yesterdayStart && $this->dateTo === $yesterdayEnd) || ($this->dateFrom === $now->copy()->subDay()->toDateString() && $this->dateTo === $now->copy()->subDay()->toDateString())) {
            return __('Yesterday');
        }

        $startDate = is_numeric($this->dateFrom)
            ? Carbon::createFromTimestamp((int) $this->dateFrom, $timezone)
            : Carbon::parse((string) $this->dateFrom, $timezone);

        $endDate = is_numeric($this->dateTo)
            ? Carbon::createFromTimestamp((int) $this->dateTo, $timezone)
            : Carbon::parse((string) $this->dateTo, $timezone);

        return match (true) {
            ! empty($this->dateFrom) && ! empty($this->dateTo) => ($startDate->toDateString() === $endDate->toDateString())
                ? $startDate->format('M j, Y')
                : $startDate->format('M j') . ' - ' . $endDate->format('M j, Y'),
            ! empty($this->dateFrom) => 'From ' . $startDate->format('M j, Y'),
            default => 'Until ' . $endDate->format('M j, Y'),
        };
    }

    public function activeTimeFilterLabel(): ?string
    {
        if (empty($this->timeFrom) && empty($this->timeTo)) {
            return null;
        }

        return match ([$this->timeFrom, $this->timeTo]) {
            ['05:00', '08:00'] => __('Early Morning (05:00 - 08:00)'),
            ['08:00', '12:00'] => __('Morning (08:00 - 12:00)'),
            ['12:00', '17:00'] => __('Afternoon (12:00 - 17:00)'),
            ['17:00', '21:00'] => __('Evening (17:00 - 21:00)'),
            ['21:00', '06:00'] => __('Night (21:00 - 06:00)'),
            default => match (true) {
                ! empty($this->timeFrom) && ! empty($this->timeTo) => $this->timeFrom . ' - ' . $this->timeTo,
                ! empty($this->timeFrom) => 'From ' . $this->timeFrom,
                default => 'Until ' . $this->timeTo,
            },
        };
    }

    /**
     * @return array<int|string, string>
     */
    public function activeDeviceFilters(): array
    {
        if (empty($this->deviceIds)) {
            return [];
        }

        $allDevices = $this->availableDevices()->keyBy('id');
        $active = [];

        foreach ($this->deviceIds as $deviceId) {
            if ($allDevices->has($deviceId)) {
                $device = $allDevices->get($deviceId);
                $active[$deviceId] = $device->name ?? $device->serial_number;
            }
        }

        return $active;
    }

    /**
     * @return array<int|string, string>
     */
    public function activePetFilters(): array
    {
        if (empty($this->petIds)) {
            return [];
        }

        $allPets = $this->availablePets()->keyBy('id');
        $active = [];

        foreach ($this->petIds as $petId) {
            if ($petId === self::UNKNOWN_PET) {
                $active[self::UNKNOWN_PET] = __('Unknown');
                continue;
            }

            if ($allPets->has($petId)) {
                $active[$petId] = $allPets->get($petId)->name;
            }
        }

        return $active;
    }

    /**
     * @return array<string, string>
     */
    public function activeTypeFilters(): array
    {
        if (empty($this->types)) {
            return [];
        }

        $allTypes = $this->availableTypes();
        $active = [];

        foreach ($this->types as $type) {
            if ($allTypes->has($type)) {
                $active[$type] = $allTypes->get($type);
            }
        }

        return $active;
    }

    /**
     * @return array<string, string>
     */
    public function activeMediaFilters(): array
    {
        if (empty($this->mediaTypes)) {
            return [];
        }

        $allMediaTypes = $this->availableMediaTypes();
        $active = [];

        foreach ($this->mediaTypes as $type) {
            if (isset($allMediaTypes[$type])) {
                $active[$type] = $allMediaTypes[$type];
            }
        }

        return $active;
    }

    /**
     * @return Collection<int, Device>
     */
    public function availableDevices(): Collection
    {
        return Device::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Pet>
     */
    public function availablePets(): Collection
    {
        return Pet::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<string, string>
     */
    public function availableTypes(): Collection
    {
        return History::query()
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type')
            ->mapWithKeys(function (string $type): array {
                return [$type => History::typeTitle($type)];
            });
    }

    /**
     * @return array<string, string>
     */
    public function availableMediaTypes(): array
    {
        return [
            self::MEDIA_TYPE_VIDEO => __('Videos'),
            self::MEDIA_TYPE_PHOTO => __('Photos / Snapshots'),
            self::MEDIA_TYPE_INFO => __('Info Only (No Media)'),
        ];
    }

    /**
     * Applies all active user filters to the query.
     */
    public function applyFilters(Builder $query): void
    {
        $this->applyDeviceFilter($query);
        $this->applyPetFilter($query);
        $this->applyTypeFilter($query);
        self::applyMediaFilter($query, $this->mediaTypes);
        self::applyDateFilter($query, $this->dateFrom, $this->dateTo);
        self::applyTimeOfDayFilter($query, $this->timeFrom, $this->timeTo);
    }

    /**
     * Returns the total count of activity records matching all currently active filters.
     */
    public function getFilteredActivitiesCount(): int
    {
        $query = History::query();
        $this->applyFilters($query);

        $total = $query->count();

        if ($this->maxResults > 0) {
            return min($this->maxResults, $total);
        }

        return $total;
    }

    public static function applyDateFilter(Builder $query, string|int|null $dateFrom, string|int|null $dateTo): void
    {
        $timezone = (string) config('app.timezone');

        if (! empty($dateFrom)) {
            $startDate = is_numeric($dateFrom)
                ? Carbon::createFromTimestamp((int) $dateFrom, $timezone)->toDateTimeString()
                : Carbon::parse((string) $dateFrom, $timezone)->startOfDay()->toDateTimeString();

            $query->where('created_at', '>=', $startDate);
        }

        if (! empty($dateTo)) {
            $endDate = is_numeric($dateTo)
                ? Carbon::createFromTimestamp((int) $dateTo, $timezone)->toDateTimeString()
                : Carbon::parse((string) $dateTo, $timezone)->endOfDay()->toDateTimeString();

            $query->where('created_at', '<=', $endDate);
        }
    }

    public static function applyTimeOfDayFilter(Builder $query, ?string $timeFrom, ?string $timeTo): void
    {
        if (empty($timeFrom) && empty($timeTo)) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        $timeExpression = match ($driver) {
            'sqlite' => "strftime('%H:%M', created_at)",
            'mysql', 'mariadb' => "TIME_FORMAT(created_at, '%H:%i')",
            'pgsql' => "to_char(created_at, 'HH24:MI')",
            default => 'TIME(created_at)',
        };

        if (! empty($timeFrom) && ! empty($timeTo)) {
            if ($timeFrom <= $timeTo) {
                $query->whereRaw("{$timeExpression} >= ? AND {$timeExpression} <= ?", [$timeFrom, $timeTo]);
            } else {
                $query->whereRaw("({$timeExpression} >= ? OR {$timeExpression} <= ?)", [$timeFrom, $timeTo]);
            }

            return;
        }

        if (! empty($timeFrom)) {
            $query->whereRaw("{$timeExpression} >= ?", [$timeFrom]);

            return;
        }

        if (! empty($timeTo)) {
            $query->whereRaw("{$timeExpression} <= ?", [$timeTo]);
        }
    }

    /**
     * @param array<string> $mediaTypes
     */
    public static function applyMediaFilter(Builder $query, array $mediaTypes): void
    {
        if (empty($mediaTypes)) {
            return;
        }

        $includeVideo = in_array(self::MEDIA_TYPE_VIDEO, $mediaTypes, true);
        $includePhoto = in_array(self::MEDIA_TYPE_PHOTO, $mediaTypes, true);
        $includeInfo = in_array(self::MEDIA_TYPE_INFO, $mediaTypes, true);

        if ($includeVideo && $includePhoto && $includeInfo) {
            return;
        }

        $query->where(function (Builder $sub) use ($includeVideo, $includePhoto, $includeInfo): void {
            $isFirst = true;

            if ($includeVideo) {
                $sub->whereHas('media', function (Builder $mediaQuery): void {
                    $mediaQuery->where('file_id', 'like', '%.ts')
                        ->orWhere('file_type', 'like', '%video%');
                });
                $isFirst = false;
            }

            if ($includePhoto) {
                $method = $isFirst ? 'where' : 'orWhere';
                $sub->{$method}(function (Builder $photoSub): void {
                    $photoSub->whereHas('media', function (Builder $mediaQuery): void {
                        $mediaQuery->where('file_id', 'not like', '%.ts')
                            ->where(function (Builder $typeQuery): void {
                                $typeQuery->whereNull('file_type')
                                    ->orWhere('file_type', 'not like', '%video%');
                            });
                    });
                });
                $isFirst = false;
            }

            if ($includeInfo) {
                $method = $isFirst ? 'whereDoesntHave' : 'orWhereDoesntHave';
                $sub->{$method}('media');
            }
        });
    }

    protected function applyDeviceFilter(Builder $query): void
    {
        if (empty($this->deviceIds)) {
            return;
        }

        $query->whereIn('device_id', $this->deviceIds);
    }

    protected function applyPetFilter(Builder $query): void
    {
        if (empty($this->petIds)) {
            return;
        }

        $hasUnknown = in_array(self::UNKNOWN_PET, $this->petIds, true);
        $concretePetIds = array_values(array_filter($this->petIds, fn ($id): bool => (string) $id !== self::UNKNOWN_PET));

        $this->filterPetsWithUnknown($query, $concretePetIds, $hasUnknown);
    }

    /**
     * @param array<int|string> $concretePetIds
     */
    protected function filterPetsWithUnknown(Builder $query, array $concretePetIds, bool $includeUnknown): void
    {
        $query->where(function (Builder $sub) use ($includeUnknown, $concretePetIds): void {
            if (! empty($concretePetIds)) {
                $sub->whereIn('pet_id', $concretePetIds);
            }

            if (! $includeUnknown) {
                return;
            }

            $orCondition = ! empty($concretePetIds);
            $this->scopeUnknownPets($sub, $orCondition);
        });
    }

    protected function scopeUnknownPets(Builder $query, bool $or = false): void
    {
        $method = $or ? 'orWhere' : 'where';

        $query->{$method}(function (Builder $unknownSub): void {
            $unknownSub->whereNull('pet_id')
                ->orWhere('pet_id', 0)
                ->orWhere('pet_id', '')
                ->orWhereNotIn('pet_id', Pet::query()->select('id'));
        });
    }

    protected function applyTypeFilter(Builder $query): void
    {
        if (empty($this->types)) {
            return;
        }

        $query->whereIn('type', $this->types);
    }

    /**
     * Returns an array of current filter parameters ready for URL building.
     *
     * @return array<string, mixed>
     */
    public function getActivityFilterQueryParams(): array
    {
        $params = [];

        if (! empty($this->deviceIds)) {
            $params[self::QUERY_PARAM_DEVICES] = $this->deviceIds;
        }
        if (! empty($this->petIds)) {
            $params[self::QUERY_PARAM_PETS] = $this->petIds;
        }
        if (! empty($this->types)) {
            $params[self::QUERY_PARAM_TYPES] = $this->types;
        }
        if (! empty($this->mediaTypes)) {
            $params[self::QUERY_PARAM_MEDIA] = $this->mediaTypes;
        }
        if (! empty($this->dateFrom)) {
            $params[self::QUERY_PARAM_DATE_FROM] = $this->dateFrom;
        }
        if (! empty($this->dateTo)) {
            $params[self::QUERY_PARAM_DATE_TO] = $this->dateTo;
        }
        if (! empty($this->timeFrom)) {
            $params[self::QUERY_PARAM_TIME_FROM] = $this->timeFrom;
        }
        if (! empty($this->timeTo)) {
            $params[self::QUERY_PARAM_TIME_TO] = $this->timeTo;
        }
        if ($this->maxResults !== self::DEFAULT_MAX_RESULTS) {
            $params[self::QUERY_PARAM_MAX_RESULTS] = $this->maxResults > 0 ? $this->maxResults : 'all';
        }

        return $params;
    }
}
