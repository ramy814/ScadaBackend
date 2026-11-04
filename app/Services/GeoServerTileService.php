<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class GeoServerTileService
{
    private const MIN_ZOOM = 11;

    private const MAX_ZOOM = 17;

    private const WEST = 34.39912130969776;

    private const EAST = 34.51853774196185;

    private const SOUTH = 31.45464230411222;

    private const NORTH = 31.55032467219495;

    public function fetch(int $zoom, int $x, int $y): Response
    {
        if (! $this->isAllowed($zoom, $x, $y)) {
            throw new InvalidArgumentException('Tile is outside the allowed map coverage.');
        }

        $response = Http::withBasicAuth(
            (string) config('geoserver.username'),
            (string) config('geoserver.password'),
        )->accept('image/png')
            ->connectTimeout(5)
            ->timeout(20)
            ->get(rtrim((string) config('geoserver.base_url'), '/').'/gwc/service/wmts', [
                'SERVICE' => 'WMTS',
                'REQUEST' => 'GetTile',
                'VERSION' => '1.0.0',
                'LAYER' => 'mog:gaza general map',
                'STYLE' => '',
                'TILEMATRIXSET' => 'EPSG:900913',
                'TILEMATRIX' => "EPSG:900913:$zoom",
                'FORMAT' => 'image/png',
                'TILECOL' => $x,
                'TILEROW' => $y,
            ]);

        if (! $response->successful() || ! str_starts_with($response->header('Content-Type'), 'image/')) {
            throw new RuntimeException('GeoServer did not return a map image.');
        }

        return $response;
    }

    private function isAllowed(int $zoom, int $x, int $y): bool
    {
        if ($zoom < self::MIN_ZOOM || $zoom > self::MAX_ZOOM) {
            return false;
        }

        return $x >= $this->longitudeToTileX(self::WEST, $zoom)
            && $x <= $this->longitudeToTileX(self::EAST, $zoom)
            && $y >= $this->latitudeToTileY(self::NORTH, $zoom)
            && $y <= $this->latitudeToTileY(self::SOUTH, $zoom);
    }

    private function longitudeToTileX(float $longitude, int $zoom): int
    {
        return (int) floor((($longitude + 180) / 360) * (2 ** $zoom));
    }

    private function latitudeToTileY(float $latitude, int $zoom): int
    {
        $radians = deg2rad($latitude);
        $mercator = log(tan($radians) + (1 / cos($radians)));

        return (int) floor(((1 - ($mercator / M_PI)) / 2) * (2 ** $zoom));
    }
}
