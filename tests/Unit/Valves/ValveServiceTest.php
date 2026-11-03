<?php

declare(strict_types=1);

namespace Tests\Unit\Valves;

use App\Modules\Valves\Models\Valve;
use App\Modules\Valves\Repositories\ValveRepository;
use App\Modules\Valves\Services\ValveService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class ValveServiceTest extends TestCase
{
    public function test_it_shapes_only_oracle_valves_located_inside_gaza_for_the_web_map(): void
    {
        $mappedValve = new Valve;
        $mappedValve->setRawAttributes([
            'w4_general_id' => 42,
            'w4_street_no' => 2000,
            'w4_valve_type' => 'GATE VALVE',
            'w4_valve_diameter' => 8,
            'w4_status' => 'NEW',
            'map_latitude' => 31.5231,
            'map_longitude' => 34.4402,
        ]);

        $unmappedValve = new Valve;
        $unmappedValve->setRawAttributes(['w4_general_id' => 43]);

        $outsideGazaValve = new Valve;
        $outsideGazaValve->setRawAttributes([
            'w4_general_id' => 44,
            'map_latitude' => -2.216,
            'map_longitude' => 27.3701,
        ]);

        $repository = $this->createMock(ValveRepository::class);
        $repository->expects(self::once())
            ->method('forMap')
            ->willReturn(new Collection([$mappedValve, $unmappedValve, $outsideGazaValve]));

        $valves = (new ValveService($repository))->getMapValves();

        self::assertCount(1, $valves);
        self::assertSame(42, $valves[0]['oid']);
        self::assertSame('WV-42', $valves[0]['code']);
        self::assertSame('محبس 42 - GATE VALVE 8', $valves[0]['name']);
        self::assertSame(31.5231, $valves[0]['latitude']);
        self::assertSame(34.4402, $valves[0]['longitude']);
        self::assertSame('NEW', $valves[0]['status']);
    }
}
