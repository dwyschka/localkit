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
 * every single-hopper class had its own hand-copied toFeed(), ported
 * commit-by-commit from D4SH (see git log: "Port D4SH schedule fixes to
 * ...").
 *
 * nextTick is the *last* entry of Time::calculateLatest()'s up-to-3
 * results, not the first/nearest - confirmed 2026-08-23 against a real app
 * capture with 2 latest entries (today + tomorrow, for an every-day
 * schedule): the app's own nextTick equalled the *later* one (tomorrow's
 * occurrence), not the sooner one. This reverses an earlier "fix" (commit
 * f0d2b9a, "reflect the nearest upcoming feed, not the farthest") made
 * without a multi-entry capture to check it against - which had also
 * overwritten FreshElement3 (D3)'s original last($latest), on the
 * assumption that it was a bug rather than what the real app does. An
 * earlier +1 bump on this value was also tried and has been removed again.
 *
 * Amounts are sent as-is, with no client-side factor multiplication -
 * an earlier scaleAmountsForWire() step (multiplying by settings.factor/
 * factor1/factor2 before sending, on the theory that the device divides by
 * its own persisted factor before dispensing) was tried and removed again
 * per direct instruction after live testing.
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

    public function toFeed(DeviceModel $device): string
    {
        $schedule = $device->configuration['schedule'];

        $latest = Time::calculateLatest($schedule);
        // calculateLatest() returns entries sorted ascending by proximity;
        // nextTick is the *last* (farthest) of the up-to-3 entries - see the
        // trait-level docblock for the live-capture evidence.
        $nextTickDefault = [...array_fill_keys(static::amountKeys(), 0), 'id' => '', 't' => 0];
        $nextTick = last($latest) ?: $nextTickDefault;

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
        $schedules = $this->device->configuration['schedule'];
        $latest = Time::calculateLatest($schedules);
        // calculateLatest() returns entries sorted ascending by proximity;
        // nextTick is the *last* (farthest) of the up-to-3 entries - see the
        // trait-level docblock for the live-capture evidence.
        $nextTickDefault = [...array_fill_keys(static::amountKeys(), 0), 'id' => '', 't' => 0];
        $nextTick = last($latest) ?: $nextTickDefault;

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
