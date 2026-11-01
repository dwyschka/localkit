<?php

namespace Tests\Unit;

use App\Petkit\Devices\YumshareDual\Device;
use App\Petkit\Devices\YumshareSolo\Device as SoloDevice;
use PHPUnit\Framework\TestCase;

class SurplusThresholdTest extends TestCase
{
    public function test_off_sends_zero_whatever_the_level(): void
    {
        $this->assertSame(0, Device::surplusThreshold(0, 3));
    }

    /** Values the real Petkit cloud sends to a D4SH (property.set capture, 2026-11-01). */
    public function test_levels_map_to_the_petkit_cloud_percentages(): void
    {
        $this->assertSame(30, Device::surplusThreshold(1, 1));
        $this->assertSame(60, Device::surplusThreshold(1, 2));
        $this->assertSame(80, Device::surplusThreshold(1, 3));
    }

    public function test_unknown_level_falls_back_to_moderate(): void
    {
        $this->assertSame(60, Device::surplusThreshold(1, 0));
    }

    public function test_d4h_uses_the_same_mapping(): void
    {
        $this->assertSame(80, SoloDevice::surplusThreshold(1, 3));
        // An older D4H config still holding the percentage (default 60) counts as "on".
        $this->assertSame(60, SoloDevice::surplusThreshold(60, 2));
        $this->assertSame(2, SoloDevice::surplusStandardForWire(0));
    }
}
