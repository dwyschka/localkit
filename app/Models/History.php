<?php

namespace App\Models;

use App\Homeassistant\Event;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

class History extends Model
{
    protected $table = 'history';
    protected $fillable = ['messageId', 'message', 'pet_id', 'device_id', 'parameters', 'type'];

    protected $casts = [
        'parameters' => 'array',
    ];

    /**
     * Every type message()/typeMeta() know about (IN_USE, CLEANING,
     * MAINTENANCE, ERROR, EAT, DRINK, DETECT) - declared as HA Event's
     * event_types up front for every device, since a given device only
     * ever fires a subset of these but discovery needs the full list
     * regardless of which device it's attached to.
     */
    public const HA_EVENT_TYPES = ['in_use', 'cleaning', 'maintenance', 'error', 'eat', 'drink', 'detect'];

    protected static function booted(): void
    {
        self::created(function (History $history) {
            if (! config('app.enable.homeassistant')) {
                return;
            }

            $device = $history->device;
            if (! $device) {
                return;
            }

            $event = new Event(
                technicalName: 'history_event',
                name: 'Activity',
                eventTypes: self::HA_EVENT_TYPES,
                icon: 'mdi:history',
            );
            $event->setDevice($device);
            $payload = $event->payload();

            $mqtt = MQTT::connection('homeassistant-publisher');
            // Discovery is retained (so the entity survives a HA restart even
            // between activities), but the event itself is not - a stale
            // retained "last event" would just refire on every reconnect.
            $mqtt->publish($event->toTopic(), json_encode($payload), 0, true);
            $mqtt->publish($payload['state_topic'], json_encode([
                'event_type' => Str::lower($history->type),
                'message' => $history->message(),
                'pet' => $history->pet?->name,
            ]), 0, false);
            $mqtt->disconnect();
        });
    }

    public function device(): BelongsTo {
        return $this->belongsTo(Device::class);
    }

    public function pet(): HasOne {
        return $this->hasOne(Pet::class, 'id', 'pet_id');
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

        switch($this->type) {
            case 'IN_USE':
                return $this->createInUseMessage();
            case 'CLEANING':
                return $this->createCleaningMessage();
            case 'MAINTENANCE':
                return $this->createMaintenanceMessage();
            case 'ERROR':
                return $this->createErrorMessage();
            case 'EAT':
                return $this->createEatMessage();
            case 'DRINK':
                return $this->createDrinkMessage();
            case 'DETECT':
                return $this->createDetectMessage();
        }
        return '';
    }

    public function title(): string {
        return __(sprintf('petkit.history.%s_title', Str::lower($this->type)));
    }

    private function createInUseMessage()
    {
        $params = $this->parameters;

        $duration = $params['time_out'] - $params['time_in'];

        return __('petkit.history.in_use', [
            'name' => $this->pet?->name ?? __('petkit.unknown'),
            // pet_weight is reported in grams by the device — show it in kg.
            'weight' => number_format($params['pet_weight'] / 1000, 2),
            'duration' => $duration,
        ]);
    }

    private function createCleaningMessage()
    {
        return __('petkit.history.cleaning', [
            'name' => $this->pet?->name ?? __('petkit.unknown')
        ]);
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

