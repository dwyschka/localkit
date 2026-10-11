<?php

namespace Tests\Unit;

use App\Petkit\Devices\PurobotUltra\Configuration;
use App\Petkit\Devices\PurobotUltra\Device;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The T6's settings are not a design, they are a transcription: the device
 * reported its whole `app_conf` section and every key in it has to exist here
 * under exactly that name, because propertyChange() diffs the stored settings
 * and forwards the changed keys straight to the device. A renamed or dropped
 * key is a setting that silently stops working.
 *
 * Deliberately a plain PHPUnit TestCase: none of this needs the database, and
 * the Feature suite's app boot does not come up outside the container.
 */
class PurobotUltraConfigurationTest extends TestCase
{
    /** The 56 app_conf keys a real T6 reported on firmware 951. */
    private const APP_CONF = [
        'timestamp_enable', 'night', 'microphone', 'cameraLight', 'toiletLight',
        'userHomeMode', 'lightMode', 'lightMultiRange', 'lightAssist', 'toneMode',
        'toneMultiRange', 'manualLock', 'clickOkEnable', 'camera', 'cameraMultiRange',
        'distrubMultiRange', 'cameraRangeTable', 'schedule', 'moveDetection',
        'moveSensitivity', 'detectInterval', 'detectMultiRange', 'soundEnable',
        'systemSoundEnable', 'volume', 'selectedSound', 'upload', 'log_upload',
        'autoIntervalMin', 'autoWork', 'fixedTimeClear', 'avoidRepeat', 'underweight',
        'lightest', 'kitten', 'sandType', 'sandSetUseConfig', 'sandFullWeight',
        'stillTime', 'unit', 'disturbMode', 'softMode', 'bury', 'deepClean',
        'sandSaving', 'preLive', 'tumbling', 'wanderDetection', 'petDetection',
        'toiletDetection', 'spary_tip_en', 'bag_tip_en', 'phDetection', 'voice',
        'packageStandard', 'capacity',
    ];

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

    public function test_every_app_conf_key_exists_as_a_property(): void
    {
        $own = $this->own(new ReflectionClass(Configuration::class));

        foreach (self::APP_CONF as $key) {
            $this->assertContains($key, $own, "app_conf key '$key' has no property");
        }
    }

    /**
     * The firmware's own spelling, kept on purpose. 'spary_tip_en' is
     * misspelled in the device and correcting it here would address a setting
     * that does not exist.
     */
    public function test_wire_keys_keep_the_firmwares_spelling(): void
    {
        $own = $this->own(new ReflectionClass(Configuration::class));

        $this->assertContains('spary_tip_en', $own);
        $this->assertNotContains('spray_tip_en', $own);

        // This device calls the OSD clock timestamp_enable; the T7 calls the
        // same thing timeDisplay, and mixing them up is a silent no-op.
        $this->assertContains('timestamp_enable', $own);
        $this->assertNotContains('timeDisplay', $own);
    }

    public function test_rules_defaults_and_casts_cover_every_property_exactly(): void
    {
        $r = new ReflectionClass(Configuration::class);
        $own = $this->own($r);
        $inst = $r->newInstanceWithoutConstructor();

        foreach (['rules', 'defaults', 'casts'] as $method) {
            $keys = array_keys($r->getMethod($method)->invoke($inst));

            // 'foo.*' validates the elements of $foo (sandFullWeight and
            // friends); it is a rule, not a key of its own.
            $keys = array_values(array_filter(
                $keys,
                fn (string $key) => !str_contains($key, '.') && !str_contains($key, '*')
            ));

            $this->assertSame([], array_values(array_diff($own, $keys)),
                "$method() is missing properties");
            $this->assertSame([], array_values(array_diff($keys, $own)),
                "$method() has entries without a property");
        }
    }

    /** Defaults are the values the real device reported, not invented ones. */
    public function test_defaults_match_the_device(): void
    {
        $r = new ReflectionClass(Configuration::class);
        $d = $r->getMethod('defaults')->invoke($r->newInstanceWithoutConstructor());

        $this->assertTrue($d['timestamp_enable']);
        $this->assertFalse($d['toiletLight']);
        $this->assertTrue($d['clickOkEnable']);
        $this->assertFalse($d['sandSaving']);
        $this->assertTrue($d['phDetection']);
        $this->assertTrue($d['wanderDetection']);
        $this->assertTrue($d['bag_tip_en']);
        $this->assertFalse($d['spary_tip_en']);
        $this->assertSame([6, 9, 10, 22], $d['packageStandard']);
        $this->assertSame([3500, 5800, 3000, 3500, 3500], $d['sandFullWeight']);
        $this->assertSame(['homeMode' => 0, 'mic' => -1, 'camera' => -1, 'ptz' => -1],
            $d['userHomeMode']);
    }

    /**
     * No consumables and no spray unit, so none of the T7's cartridge/liner
     * properties may have come along with the copy.
     */
    public function test_the_t7s_own_hardware_did_not_come_along(): void
    {
        $own = $this->own(new ReflectionClass(Configuration::class));

        foreach (['n60Durability', 'n60NextChange', 'cardboardDurability',
                  'cardboardNextChange', 'autoSpray', 'deepSpray', 'urine',
                  'occult', 'microlight', 'downpos', 'petSensitivity',
                  'softModeClean', 'toiletLightAssist', 'wifiLightAssist'] as $key) {
            $this->assertNotContains($key, $own, "T7-only property '$key' leaked in");
        }
    }

    /**
     * Only the work modes two independent litter boxes agree on are offered.
     * ctrl_t6 passes the start number straight through to the HMI without
     * interpreting it, so this device's extra cycles (dump, smooth, melt,
     * correct, package) have no number that can be read out of the firmware -
     * and a guessed number moves a motor.
     */
    public function test_only_evidenced_actions_are_offered(): void
    {
        $r = new ReflectionClass(Device::class);
        $actions = $r->getProperty('actions')->getDefaultValue();

        $this->assertContains('start_clean', $actions);
        $this->assertContains('deodorize', $actions);
        $this->assertContains('start_lightning', $actions);

        $this->assertNotContains('reset_n60', $actions);
        $this->assertNotContains('reset_cardboard', $actions);
    }

    public function test_device_name(): void
    {
        $this->assertSame('Purobot Ultra', Device::deviceName());
    }
}
