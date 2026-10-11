<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Cross-device structural check on every Configuration DTO.
 *
 * It exists because of one line. The PuroBot Crystal cast its OSD clock as
 * `'time' => new BooleanCast()` while the property is `$timeDisplay`: the cast
 * applied to a property that does not exist, and the one that does had none.
 * Nothing noticed - not the build, not the device, not the panel - because an
 * entry for a missing key is simply never reached, and a property without a
 * cast quietly takes whatever came in. The d4h and d4sh had it right all along,
 * which is what makes it a typo rather than a convention.
 *
 * So: a key in rules()/defaults()/casts() that no property answers to is an
 * error here. Laravel's nested-array rules ('foo.*') are not keys in that
 * sense and are skipped.
 *
 * The reverse direction - a property with no entry - is deliberately NOT
 * asserted globally: several devices carry internal-only properties that are
 * neither validated nor cast, and tightening that is a separate decision per
 * device. PurobotUltraConfigurationTest does assert it for the T6.
 */
class DeviceConfigurationIntegrityTest extends TestCase
{
    public static function deviceProvider(): array
    {
        $dir = dirname(__DIR__, 2) . '/app/Petkit/Devices';
        $out = [];

        foreach (glob($dir . '/*/Configuration.php') as $file) {
            $device = basename(dirname($file));
            $out[$device] = [$device, "App\\Petkit\\Devices\\$device\\Configuration"];
        }

        return $out;
    }

    #[DataProvider('deviceProvider')]
    public function test_no_rule_default_or_cast_names_a_property_that_does_not_exist(
        string $device,
        string $class
    ): void {
        $this->assertTrue(class_exists($class), "$device has no Configuration class");

        $r = new ReflectionClass($class);
        $own = [];
        foreach ($r->getProperties(ReflectionProperty::IS_PUBLIC) as $p) {
            if ($p->getDeclaringClass()->getName() === $class) {
                $own[] = $p->getName();
            }
        }

        $instance = $r->newInstanceWithoutConstructor();

        foreach (['rules', 'defaults', 'casts'] as $method) {
            $keys = array_keys($r->getMethod($method)->invoke($instance));

            // 'foo.*' validates the elements of $foo; it is not its own key.
            $keys = array_values(array_filter(
                $keys,
                fn (string $key) => !str_contains($key, '.') && !str_contains($key, '*')
            ));

            $ghosts = array_values(array_diff($keys, $own));

            $this->assertSame(
                [],
                $ghosts,
                sprintf(
                    '%s::%s() names %s, which is not a property of it - most likely a '
                    . 'misspelled property name, in which case the real property has no %s.',
                    $device,
                    $method,
                    implode(', ', array_map(fn ($g) => "'$g'", $ghosts)),
                    $method === 'rules' ? 'validation' : $method
                )
            );
        }
    }
}
