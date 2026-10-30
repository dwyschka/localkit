<?php

namespace Tests\Unit;

use App\Petkit\Devices\PurobotMax\Configuration;
use App\Petkit\Devices\PurobotMax\Device;
use App\Jobs\PublishDiscernUpdate;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Deliberately a plain PHPUnit TestCase: none of this needs the database.
 *
 * The T5 wire keys are a transcription of ctrl_t5's
 * parse_recv_property_set_normal()/parse_recv_property_set_algo_param(), not
 * a design. Renaming one silently turns the setting into a no-op, so this
 * test pins the names that differ from the T6.
 */
class PurobotMaxConfigurationTest extends TestCase
{
    private function own(ReflectionClass $r): array
    {
        $own = [];
        foreach ($r->getProperties(ReflectionProperty::IS_PUBLIC) as $p) {
            if ($p->getDeclaringClass()->getName() === $r->getName()) {
                $own[] = $p->getName();
            }
        }

        return $own;
    }

    public function test_rules_defaults_and_casts_cover_every_property_exactly(): void
    {
        $r = new ReflectionClass(Configuration::class);
        $own = $this->own($r);
        $inst = $r->newInstanceWithoutConstructor();

        foreach (['rules', 'defaults', 'casts'] as $method) {
            $keys = array_keys($r->getMethod($method)->invoke($inst));

            $keys = array_values(array_filter(
                $keys,
                fn (string $key) => ! str_contains($key, '.') && ! str_contains($key, '*')
            ));

            // The two next-change timestamps are `mixed` (raw Unix
            // timestamps, same as the T4/T7 implementations) and have no
            // matching Cast class, so casts() intentionally leaves them out.
            $expectedOwn = $method === 'casts'
                ? array_values(array_diff($own, ['n50NextChange', 'n60NextChange']))
                : $own;

            $this->assertSame([], array_values(array_diff($expectedOwn, $keys)),
                "$method() is missing properties");
            $this->assertSame([], array_values(array_diff($keys, $own)),
                "$method() has entries without a property");
        }
    }

    /**
     * Keys ctrl_t5 actually reads from thing/service/property/set.
     */
    public function test_settable_ctrl_t5_keys_exist(): void
    {
        $own = $this->own(new ReflectionClass(Configuration::class));

        foreach ([
            // normal parser
            'lightMode', 'lightMultiRange', 'lightAssist', 'toneMode', 'toneMultiRange',
            'schedule', 'distrubMultiRange', 'cameraMultiRange', 'manualLock',
            'clickOkEnable', 'unit', 'language', 'camera', 'microphone', 'cameraLight',
            'toiletLight', 'night', 'timeDisplay', 'upload', 'tumbling', 'kitten',
            'autoWork', 'fixedTimeClear', 'deepClean', 'sandSaving', 'avoidRepeat',
            'autoIntervalMin', 'underweight', 'stillTime', 'bury', 'disturbMode',
            'sandType', 'lightest', 'soundList', 'deepSpray', 'fixedTimeSpray',
            'autoSpray', 'sprayDays', 'downpos',
            // algo parser
            'moveTracking', 'moveSensitivity', 'petDetection', 'petTracking',
            'petSensitivity', 'toiletDetection', 'phDetection', 'voice',
            'detectInterval', 'detectMultiRange',
        ] as $key) {
            $this->assertContains($key, $own, "settable key '$key' has no property");
        }
    }

    /**
     * These are the T6 wire names the T5 does NOT use. Sending them is a
     * silent no-op in ctrl_t5, so they must not leak into the T5 DTO.
     */
    public function test_t6_only_or_renamed_keys_did_not_come_along(): void
    {
        $own = $this->own(new ReflectionClass(Configuration::class));

        foreach ([
            'timestamp_enable', 'wanderDetection', 'userHomeMode', 'cameraOff',
            'cameraInward', 'noSound', 'homeMode', 'smartFrame', 'packageStandard',
            'bag_tip_en', 'spary_tip_en', 'log_upload', 'cameraRangeTable',
            'moveDetection', 'toiletDetection_Enable', 'pet_det_Enable',
        ] as $key) {
            $this->assertNotContains($key, $own, "T6-only key '$key' leaked into the T5");
        }

        // ctrl_t5 reads `timeDisplay`, not the T6's `timestamp_enable`.
        $this->assertContains('timeDisplay', $own);
    }

    /**
     * T5 has no waste-bag/NFC pairing (dispatch_handler_trash_bag_auth is an
     * empty stub) and no sealing door, so neither action may be offered.
     */
    public function test_bag_and_door_actions_are_not_offered(): void
    {
        $r = new ReflectionClass(Device::class);
        $actions = $r->getProperty('actions')->getDefaultValue();

        $this->assertContains('start_clean', $actions);
        $this->assertContains('clean_litter', $actions);
        $this->assertContains('start_odour', $actions);
        $this->assertContains('recover', $actions);
        $this->assertContains('smooth', $actions);
        $this->assertContains('calibrate', $actions);
        $this->assertContains('start_maintenance', $actions);
        $this->assertContains('stop_maintenance', $actions);
        $this->assertContains('reset_n50', $actions);
        $this->assertContains('reset_n60', $actions);

        $this->assertNotContains('pack_waste_bag', $actions);
        $this->assertNotContains('toggle_door', $actions);
    }

    public function test_camera_working_time_is_a_from_till_minute_range(): void
    {
        $r = new ReflectionClass(Configuration::class);
        $defaults = $r->getMethod('defaults')->invoke($r->newInstanceWithoutConstructor());

        $this->assertIsArray($defaults['cameraMultiRange']);
        $this->assertSame('cameraMultiRange', $defaults['cameraMultiRange']['name']);
        $this->assertSame([['from' => 0, 'till' => 1440]], $defaults['cameraMultiRange']['ranges']);
    }

    public function test_fixed_schedule_enables_are_booleans(): void
    {
        $r = new ReflectionClass(Configuration::class);
        $defaults = $r->getMethod('defaults')->invoke($r->newInstanceWithoutConstructor());

        $this->assertIsBool($defaults['fixedTimeClear']);
        $this->assertIsBool($defaults['fixedTimeSpray']);
    }

    public function test_t5_is_registered_for_discern_updates(): void
    {
        $this->assertContains('t5', PublishDiscernUpdate::DISCERN_CAPABLE_DEVICE_TYPES);
    }

    public function test_device_name(): void
    {
        $this->assertSame('Purobot Max', Device::deviceName());
    }
}
