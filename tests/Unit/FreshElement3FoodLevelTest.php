<?php

namespace Tests\Unit;

use App\Petkit\Devices\FreshElement3\Device;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The FE3 hopper sensor reports 2 when it sees enough food, 1 when the food is
 * running low and 0 when it is empty. Only 0 used to raise a fault, so a low
 * hopper - the state you actually want to act on before a feed is missed -
 * read as healthy.
 */
class FreshElement3FoodLevelTest extends TestCase
{
    private static function foodError(int $level, ?string $currentError): ?string
    {
        $method = new ReflectionMethod(Device::class, 'foodError');
        $method->setAccessible(true);

        return $method->invoke(null, $level, $currentError);
    }

    public static function levels(): array
    {
        return [
            'empty raises the empty fault' => [0, null, 'food_empty'],
            'low raises the low fault' => [1, null, 'food_low'],
            'enough clears the low fault' => [2, 'food_low', null],
            'enough clears the empty fault' => [2, 'food_empty', null],
            'enough leaves no fault alone' => [2, null, null],
            'enough keeps an unrelated fault' => [2, 'bowl', 'bowl'],
            'low replaces an unrelated fault' => [1, 'bowl', 'food_low'],
            'empty replaces an unrelated fault' => [0, 'bowl', 'food_empty'],
        ];
    }

    #[DataProvider('levels')]
    public function test_the_hopper_level_maps_onto_the_device_error(
        int $level,
        ?string $currentError,
        ?string $expected
    ): void {
        $this->assertSame($expected, self::foodError($level, $currentError));
    }
}
