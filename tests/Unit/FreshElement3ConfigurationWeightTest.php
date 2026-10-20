<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Petkit\Devices\FreshElement3\Configuration;
use Tests\TestCase;

/**
 * The FE3 reports its bowl scale reading in event state payloads, not in its
 * settings. Keep that reading in the device configuration so the panel and
 * Home Assistant can show it without a schema change.
 */
class FreshElement3ConfigurationWeightTest extends TestCase
{
    private function device(array $states): Device
    {
        return new Device([
            'device_type' => 'd3',
            'configuration' => [
                'states' => $states,
                'consumables' => [],
                'settings' => [],
                'schedule' => [],
            ],
        ]);
    }

    public function test_the_bowl_weight_round_trips_through_the_configuration_dto(): void
    {
        $configuration = Configuration::fromDevice($this->device(['weight' => 332]));

        $this->assertSame(332, $configuration->weight);
        $this->assertSame(332, $configuration->toArray()['states']['weight']);
    }

    public function test_a_missing_weight_stays_null(): void
    {
        $configuration = Configuration::fromDevice($this->device([]));

        $this->assertNull($configuration->weight);
        $this->assertNull($configuration->toArray()['states']['weight']);
    }
}
