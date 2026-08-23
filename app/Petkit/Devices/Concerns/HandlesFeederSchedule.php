<?php

namespace App\Petkit\Devices\Concerns;

use App\Helpers\Time;
use App\Jobs\FeedRealtime;
use App\Jobs\ServiceStart;
use App\Models\Device as DeviceModel;

/**
 * Single source of truth for "how many hopper amounts does this feeder's
 * wire format carry". schedule.md's D4/D4SH cross-check (§4e) found the
 * single-vs-dual split hard-coded three different ways across the feeder
 * Device classes - a per-item 'a' vs 'a1'/'a2' key, a settings 'amount' vs
 * 'amount1'/'amount2' pair, and (in DeviceActions.php) an
 * `instanceof YumshareDual\Device` check to pick the Filament form - and
 * every single-hopper class had its own hand-copied toFeed()/
 * scaleAmountsForWire(), ported commit-by-commit from D4SH (see git log:
 * "Port D4SH schedule fixes to ..."). FreshElement3 (D3) never got that
 * port and had drifted from the other three - it used last($latest)
 * instead of head() (farthest upcoming feed instead of nearest) - fixed
 * here as a side effect of there now being one implementation instead of
 * four, not as a separate change. nextTick is sent as the raw seconds
 * from Time::calculateLatest() - an earlier +1 bump on this value was
 * tried and has been removed again.
 *
 * A class using this trait need only declare `public const FEEDER_COUNT`
 * (1 for single-hopper, 2 for dual - schedule.md §4e independently
 * confirmed via the D4 firmware's own strings that D4/D4H/D3 are
 * single-hopper at the wire-protocol level; only D4SH is dual) and a
 * `protected DeviceModel $device` property (every feeder Device class
 * already has one, constructor-promoted) - toFeedGet()/startFeeding() read
 * settings off it directly, matching how the classes this was extracted
 * from already worked.
 */
trait HandlesFeederSchedule
{
    /** @return string[] the per-item amount keys this device's wire format uses: ['a'] or ['a1', 'a2']. */
    public static function amountKeys(): array
    {
        return static::FEEDER_COUNT > 1 ? ['a1', 'a2'] : ['a'];
    }

    /**
     * The device divides each hopper's amount by its own persisted factor
     * before dispensing (schedule.md §2c/§3e - confirmed live on D4SH: a raw
     * a1=1/a2=1 with factor1/factor2 already >1 on-device came back as
     * amount_l=0, amount_r=0 after that division). Storage/UI keeps amounts
     * as plain human units; this scales them up to whatever the on-device
     * division will bring back down to the intended amount, right before
     * the schedule is put on the wire - not persisted, so a later factor
     * change doesn't require touching every stored item. A device whose
     * settings carry no factor key at all (D3 has none in its Configuration
     * DTO) gets `max(1, ... ?? 1)` = 1 per hopper, i.e. this is a no-op for
     * it rather than a fabricated scaling behavior.
     */
    private function scaleAmountsForWire(array $schedule, array $settings): array
    {
        $factors = static::FEEDER_COUNT > 1
            ? ['a1' => max(1, (int)($settings['factor1'] ?? 1)), 'a2' => max(1, (int)($settings['factor2'] ?? 1))]
            : ['a' => max(1, (int)($settings['factor'] ?? 1))];

        foreach ($schedule as &$group) {
            foreach ($group['it'] as &$item) {
                foreach ($factors as $key => $factor) {
                    if (array_key_exists($key, $item)) {
                        $item[$key] = (int)$item[$key] * $factor;
                    }
                }
            }
            unset($item);
        }
        unset($group);

        return $schedule;
    }

    public function toFeed(DeviceModel $device): string
    {
        $schedule = $this->scaleAmountsForWire($device->configuration['schedule'], $device->configuration['settings']);

        $latest = Time::calculateLatest($schedule);
        // calculateLatest() returns entries sorted ascending by proximity, so
        // the nearest upcoming feed - what "nextTick" should mean - is the
        // first element, not the last (last was the farthest of the up-to-3
        // entries).
        $nextTickDefault = [...array_fill_keys(static::amountKeys(), 0), 'id' => '', 't' => 0];
        $nextTick = head($latest) ?: $nextTickDefault;

        return json_encode([
            'schedule' => array_map(
                fn(array $s) => Time::normalizeScheduleGroupForWire($s),
                $schedule
            ),
            'nextTick' => $nextTick['t'],
            'latest' => $latest,
        ]);
    }

    /**
     * The `/dev_feed_get` GET-style response (schedule.md §1's "feed_get"
     * re-pull path) - built the same way as toFeed()'s push payload, plus a
     * synthetic all-remaining-days-off group so the app can render every day
     * of the week, and a raw 'itemJsonString' per group for whatever consumes
     * this response pre-decoded. Only YumshareSolo/YumshareDual expose this;
     * folded into the trait rather than copied because it shared the exact
     * same amountKeys()-shaped nextTick default as toFeed() did.
     */
    public function toFeedGet(): array
    {
        $unusedDays = [1, 2, 3, 4, 5, 6, 7];
        $schedules = $this->scaleAmountsForWire($this->device->configuration['schedule'], $this->device->configuration['settings']);
        $latest = Time::calculateLatest($schedules);
        // calculateLatest() returns entries sorted ascending by proximity, so
        // the nearest upcoming feed - what "nextTick" should mean - is the
        // first element, not the last (last was the farthest of the up-to-3
        // entries).
        $nextTickDefault = [...array_fill_keys(static::amountKeys(), 0), 'id' => '', 't' => 0];
        $nextTick = head($latest) ?: $nextTickDefault;

        foreach ($schedules as &$schedule) {
            $schedule['itemJsonString'] = json_encode($schedule['it']);

            foreach (explode(',', $schedule['re']) as $re) {
                unset($unusedDays[intval($re) - 1]);
            }
        }
        unset($schedule);

        if (!empty($unusedDays)) {
            $schedules[] = [
                're' => implode(',', $unusedDays),
                'it' => [],
                'itemJsonString' => '[]',
            ];
        }

        $schedules = array_map(
            fn(array $schedule) => Time::normalizeScheduleGroupForWire($schedule),
            $schedules
        );

        return [
            'schedule' => $schedules,
            'nextTick' => $nextTick['t'],
            'latest' => $latest,
        ];
    }

    public function startFeeding(DeviceModel $record, ?int $amount = null, ?int $amount2 = null): void
    {
        $settings = $this->device->configuration['settings'];

        if (static::FEEDER_COUNT > 1) {
            $amount ??= $settings['amount1'] ?? 1;
            $amount2 ??= $settings['amount2'] ?? 1;

            // Dual is a two-hopper feeder, so feed_realtime carries amount1/amount2.
            FeedRealtime::dispatchSync($record, $amount, $amount2);
            ServiceStart::dispatchSync($record, $amount + $amount2);

            return;
        }

        $amount ??= $settings['amount'] ?? 10;

        FeedRealtime::dispatchSync($record, $amount);
        ServiceStart::dispatchSync($record, $amount);
    }
}
