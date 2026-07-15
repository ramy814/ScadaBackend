<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Models\Wells\Well;
use App\Services\Wells\DieselFuelCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DieselFuelCalculatorTest extends TestCase
{
    #[DataProvider('tankVolumes')]
    public function test_it_calculates_fuel_liters_by_tank_shape(
        string $shape,
        float $diameter,
        float $tankHeight,
        float $dieselHeightCm,
        float $expectedLiters,
    ): void {
        $well = new Well;
        $well->forceFill([
            'TANK_SHAPE' => $shape,
            'TANK_DIAMETER' => (string) $diameter,
            'TANK_HEIGHT' => (string) $tankHeight,
        ]);

        $actual = (new DieselFuelCalculator)->litersForWell($well, $dieselHeightCm);

        self::assertEqualsWithDelta($expectedLiters, $actual, 0.01);
    }

    public static function tankVolumes(): array
    {
        return [
            'vertical cylinder' => ['اسطواني عمودي', 2, 3, 100, 3141.59],
            'horizontal half-full cylinder' => ['اسطواني أفقي', 2, 3, 100, 4712.39],
            'cube' => ['مكعب', 2, 3, 100, 4000.00],
        ];
    }

    public function test_it_returns_null_when_tank_dimensions_are_incomplete(): void
    {
        $well = new Well;
        $well->forceFill(['TANK_SHAPE' => 'مكعب', 'TANK_DIAMETER' => '0', 'TANK_HEIGHT' => '2']);

        self::assertNull((new DieselFuelCalculator)->litersForWell($well, 50));
    }

    public function test_an_empty_tank_has_zero_liters(): void
    {
        $well = new Well;
        $well->forceFill(['TANK_SHAPE' => 'اسطواني عمودي', 'TANK_DIAMETER' => '2', 'TANK_HEIGHT' => '3']);

        self::assertSame(0.0, (new DieselFuelCalculator)->litersForWell($well, 0));
    }

    public function test_it_calculates_the_full_tank_capacity(): void
    {
        $well = new Well;
        $well->forceFill([
            'TANK_SHAPE' => 'اسطواني أفقي',
            'TANK_DIAMETER' => '2',
            'TANK_HEIGHT' => '3',
        ]);

        self::assertEqualsWithDelta(
            9424.78,
            (new DieselFuelCalculator)->tankCapacityLiters($well),
            0.01,
        );
    }
}
