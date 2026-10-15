<?php

namespace Tests\Unit;

use App\Petkit\Devices\PurobotMax\Configuration;
use App\Petkit\Devices\PurobotMax\Device;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Deliberately a plain PHPUnit TestCase: none of this needs the database,
 * and the Feature suite's app boot does not come up outside the container.
 */
class PurobotMaxConfigurationTest extends TestCase
{
    private function own(ReflectionClass $r): array
    {
        $own = [];
        foreach ($r->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
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
                fn (string $key) => !str_contains($key, '.') && !str_contains($key, '*')
            ));

            // n50NextChange is `mixed` (a raw unix timestamp, same as the
            // PuraMax/T4 implementation it was copied from) and has no
            // matching Cast class, so casts() intentionally leaves it out.
            $expectedOwn = $method === 'casts'
                ? array_values(array_diff($own, ['n50NextChange']))
                : $own;

            $this->assertSame([], array_values(array_diff($expectedOwn, $keys)),
                "$method() is missing properties");
            $this->assertSame([], array_values(array_diff($keys, $own)),
                "$method() has entries without a property");
        }
    }

    /**
     * No camera on this implementation yet (deliberately out of scope) and
     * no waste-bag/NFC mechanism at all on this device (dispatch_handler_
     * trash_bag_auth is an empty stub in ctrl_t5, and there is no
     * /dev_package_auth route - see t5/ctrl_actions.txt §4) - none of the
     * T6's camera or bag properties may have come along with the copy.
     */
    public function test_camera_and_bag_properties_did_not_come_along(): void
    {
        $own = $this->own(new ReflectionClass(Configuration::class));

        foreach ([
            'camera', 'cameraLight', 'microphone', 'night', 'preLive',
            'cameraMultiRange', 'cameraRangeTable', 'moveDetection',
            'moveSensitivity', 'petDetection', 'wanderDetection',
            'detectInterval', 'detectMultiRange', 'userHomeMode',
            'timestamp_enable', 'bag_tip_en', 'packageStandard',
        ] as $key) {
            $this->assertNotContains($key, $own, "camera/bag property '$key' leaked in");
        }
    }

    /**
     * Only the actions t5/actions.txt found a confirmed app-triggered start
     * button for are offered - workMode 5 (calibration), 6 (sand-lack
     * reset) and 8 (setup prep) have no such button, so no button is
     * offered for them either.
     */
    public function test_only_evidenced_actions_are_offered(): void
    {
        $r = new ReflectionClass(Device::class);
        $actions = $r->getProperty('actions')->getDefaultValue();

        $this->assertContains('start_clean', $actions);
        $this->assertContains('clean_litter', $actions);
        $this->assertContains('start_odour', $actions);
        $this->assertContains('recover', $actions);
        $this->assertContains('level', $actions);
        $this->assertContains('start_maintenance', $actions);
        $this->assertContains('stop_maintenance', $actions);

        $this->assertNotContains('pack_waste_bag', $actions);
        $this->assertNotContains('toggle_door', $actions);
    }

    public function test_device_name(): void
    {
        $this->assertSame('Purobot Max', Device::deviceName());
    }
}
