<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class History extends Model
{
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
    public function eventDuration(): int {
        if (isset($this->parameters['event_start'], $this->parameters['event_end'])) {
            return $this->parameters['event_end'] - $this->parameters['event_start'];
        }

        return (int) $this->duration();
    }

    public function message(): string {

        $message = match ($this->type) {
            'IN_USE' => $this->createInUseMessage(),
            'CLEANING' => $this->createCleaningMessage(),
            'MAINTENANCE' => $this->createMaintenanceMessage(),
            'ERROR' => $this->createErrorMessage(),
            'EAT' => $this->createEatMessage(),
            'DRINK' => $this->createDrinkMessage(),
            'DETECT' => $this->createDetectMessage(),
            default => '',
        };

        // pet_id can resolve after the fact (async pet_discern) and applies
        // regardless of event type - surface the name whenever we have one,
        // rather than only for the handful of message strings that embed it.
        // Device faults (ERROR) aren't attributable to a specific pet.
        if ($message !== '' && $this->type !== 'ERROR' && $this->pet) {
            $message .= ' · ' . $this->pet->name;
        }

        return $message;
    }

    public function title(): string {
        return __(sprintf('petkit.history.%s_title', Str::lower($this->type)));
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

