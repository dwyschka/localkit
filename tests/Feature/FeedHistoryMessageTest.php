<?php

namespace Tests\Feature;

use App\Models\History;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A feed_over row used to render as "Feeding" no matter what the device
 * reported, so a refused scheduled feed looked like a successful one. The
 * outcome is in the event content: result 0 means it dispensed, any other
 * result means it did not.
 */
class FeedHistoryMessageTest extends TestCase
{
    use RefreshDatabase;

    private function feed(array $parameters): History
    {
        return History::create([
            'messageId' => 'feed_test_1',
            'type' => 'WORKING',
            'parameters' => $parameters,
            'device_id' => null,
            'pet_id' => null,
        ]);
    }

    public function test_a_completed_feed_reports_the_amount_dispensed(): void
    {
        $history = $this->feed([
            'result' => 0,
            'err_code' => 0,
            'real_amount' => 16,
        ]);

        $this->assertSame('Dispensed 16 g', $history->message());
    }

    public function test_a_failed_feed_reports_the_device_result_and_error(): void
    {
        $history = $this->feed([
            'result' => 6,
            'err_code' => 16,
            'real_amount' => 0,
        ]);

        $this->assertSame(
            'Feed failed (result 6, error 16) - 0 g dispensed',
            $history->message()
        );
    }

    public function test_a_feed_without_an_outcome_is_shown_as_started(): void
    {
        $history = $this->feed([
            'id' => null,
            'manual' => 1,
        ]);

        $this->assertSame('Feeding started', $history->message());
    }
}
