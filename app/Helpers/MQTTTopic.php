<?php

namespace App\Helpers;

class MQTTTopic
{
    /**
     * The `method` an MQTT message on $topic carries, derived from the topic
     * itself - `/sys/<pk>/<dev>/thing/event/feed_over/post` becomes
     * `thing.event.feed_over.post`.
     *
     * Needed because the device's `thing/event/<event>/post` messages carry no
     * `method` field of their own (only `id`, `version`, `params` and
     * `sys.ack`), while the ack it expects back does - see SuccessResource.
     *
     * Returns null for a topic with no `/thing/` segment, which has no method
     * to derive (the localkit-internal and OTA topics).
     */
    public static function topic2Method(string $topic): ?string
    {
        $parts = explode('/thing/', $topic, 2);

        if (count($parts) < 2 || $parts[1] === '') {
            return null;
        }

        // '_reply' stripped so a reply topic yields the same method as the
        // post it answers.
        return 'thing.' . str_replace(['/', '_reply'], ['.', ''], $parts[1]);
    }
}
