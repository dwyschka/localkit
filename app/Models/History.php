<?php

namespace App\Models;

use App\Petkit\DeviceStates;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class History extends Model
{
    public const DATETIME_FORMAT = 'LL · LT';
    public const DATETIME_WITH_SECONDS_FORMAT = 'LL · LTS';

    protected $table = 'history';
    protected $fillable = ['messageId', 'message', 'pet_id', 'device_id', 'parameters', 'type'];

    protected $casts = [
        'parameters' => 'array',
    ];

    public function pet(): HasOne {
        return $this->hasOne(Pet::class, 'id', 'pet_id');
    }

    public function device(): BelongsTo {
        return $this->belongsTo(Device::class);
    }

    /**
     * Determines whether this history entry represents a pet-attributable activity.
     */
    public function isPetActivity(): bool
    {
        return $this->pet !== null || ! DeviceStates::isDeviceState($this->type);
    }

    /**
     * Camera captures uploaded under the same eventId this entry's messageId
     * carries (see DevUploadFileInfoV2Controller / PetkitYumshareDual's
     * pet_detect/eat_start handlers - both use the device's own event_id as
     * the messageId, which the camera later tags every clip from the same
     * event with).
     */
    public function media(): HasMany {
        return $this->hasMany(MediaFile::class, 'event_id', 'messageId');
    }

    public function duration(): float {
        return $this->created_at->diffInSeconds($this->updated_at);
    }

    /**
     * event_start/event_end (when the follow-up event's payload carries
     * them - confirmed on W7H's drink_over) are the device's own precise
     * timestamps for the event, not MQTT-arrival timestamps - prefer them
     * over duration()'s created_at/updated_at, which has processing/network
     * latency baked in on both ends.
     */
    public function eventDuration(): int
    {
        if (isset($this->parameters['event_start'], $this->parameters['event_end'])) {
            return max(0, (int) ($this->parameters['event_end'] - $this->parameters['event_start']));
        }

        if (isset($this->parameters['time_in'], $this->parameters['time_out'])) {
            return max(0, (int) ($this->parameters['time_out'] - $this->parameters['time_in']));
        }

        if (isset($this->parameters['start_time'], $this->parameters['over_time'])) {
            return max(0, (int) ($this->parameters['over_time'] - $this->parameters['start_time']));
        }

        return max(0, (int) $this->duration());
    }

    /**
     * Formats seconds into a compact human-readable duration string.
     *
     * Supports fractional seconds for sub-minute benchmark timings (e.g., 23.3s),
     * as well as multi-unit breakdowns for longer durations (e.g., 1d 2h 15m 4s).
     *
     * Examples:
     * 23.3   => 23.3s
     * 30     => 30s
     * 60     => 1m
     * 75     => 1m 15s
     * 300    => 5m
     * 3665   => 1h 1m 5s
     * 86400  => 1d
     * 90061  => 1d 1h 1m 1s
     */
    public static function formatHumanDuration(float|int $seconds): string
    {
        if ($seconds < 60) {
            return is_float($seconds) && fmod($seconds, 1.0) !== 0.0
                ? sprintf('%.1fs', $seconds)
                : sprintf('%ds', (int) round($seconds));
        }

        $totalSeconds = (int) round($seconds);
        $days = intdiv($totalSeconds, 86400);
        $hours = intdiv($totalSeconds % 86400, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $remainingSeconds = $totalSeconds % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = "{$days}d";
        }

        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }

        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }

        if ($remainingSeconds > 0) {
            $parts[] = "{$remainingSeconds}s";
        }

        return implode(' ', $parts) ?: '0s';
    }

    /**
     * Returns the event duration in a compact human-readable format.
     */
    public function eventHumanDuration(): string
    {
        return self::formatHumanDuration($this->eventDuration());
    }
    public function message(): string
    {
        return match ($this->type) {
            'IN_USE' => $this->createInUseMessage(),
            'CLEANING' => $this->createCleaningMessage(),
            'MAINTENANCE' => $this->createMaintenanceMessage(),
            'ERROR' => $this->createErrorMessage(),
            'EAT' => $this->createEatMessage(),
            'DRINK' => $this->createDrinkMessage(),
            'DETECT' => $this->createDetectMessage(),
            default => '',
        };
    }

    public function title(): string {
        return self::typeTitle($this->type);
    }

    public static function typeTitle(?string $type): string {
        if ($type === null || $type === '') {
            return __('Unknown');
        }

        $key = sprintf('petkit.history.%s_title', Str::lower($type));
        $translation = __($key);

        return ($translation === $key) ? Str::headline($type) : $translation;
    }

    private function createInUseMessage()
    {
        $params = $this->parameters;

        $duration = $params['time_out'] - $params['time_in'];

        // Not every litter box reports pet_weight (e.g. Purobot Crystal has
        // no weight sensor), so there's no pet to match by weight either.
        if (!isset($params['pet_weight'])) {
            return __('petkit.history.in_use_no_weight', [
                'duration' => $duration,
            ]);
        }

        return __('petkit.history.in_use', [
            // pet_weight is reported in grams by the device — show it in kg.
            'weight' => number_format($params['pet_weight'] / 1000, 2),
            'duration' => $duration,
        ]);
    }

    private function createCleaningMessage()
    {
        return __('petkit.history.cleaning');
    }

    private function createErrorMessage()
    {
        return __(sprintf('petkit.error.%s', $this->parameters['error']));
    }

    private function createMaintenanceMessage()
    {
        $params = $this->parameters;

        $duration = 0;
        if(isset($params['over_time']) && isset($params['start_time'])) {
            $duration = $params['over_time'] - $params['start_time'];
        }


        return __('petkit.history.maintenance', [
            'duration' => $duration
        ]);
    }

    private function createEatMessage()
    {
        return __('petkit.history.eat', [
            'duration' => $this->eventDuration(),
        ]);
    }

    private function createDrinkMessage()
    {
        return __('petkit.history.drink', [
            'duration' => $this->eventDuration(),
        ]);
    }

    private function createDetectMessage()
    {
        $count = $this->parameters['count'] ?? 0;

        return $count > 0
            ? __('petkit.history.detect_count', ['count' => $count])
            : __('petkit.history.detect');
    }
}
