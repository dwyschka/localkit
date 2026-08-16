<?php

namespace App\Homeassistant;

use App\Models\Device;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

/**
 * Fires a Home Assistant `event` entity - called directly from a device
 * class's topic callback right where something actually happens (e.g.
 * drink_start/drink_over on the W7H), not off a model hook. Deliberately
 * takes plain values, not an Eloquent model - this is just "an event
 * happened on this device", nothing more.
 */
class EventPublisher
{
    public static function publish(Device $device, string $eventType, array $attributes = []): void
    {
        if (! config('app.enable.homeassistant')) {
            return;
        }

        $event = new Event(
            technicalName: 'history_event',
            name: 'Activity',
            eventTypes: ['eat_start', 'eat_over', 'drink_start', 'drink_over', 'detect', 'in_use', 'cleaning', 'maintenance', 'error'],
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
            'event_type' => Str::lower($eventType),
            ...$attributes,
        ]), 0, false);
        $mqtt->disconnect();
    }
}
