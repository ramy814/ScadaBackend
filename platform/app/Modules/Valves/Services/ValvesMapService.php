<?php

declare(strict_types=1);

namespace App\Modules\Valves\Services;

use Illuminate\Support\Collection;

class ValvesMapService
{
    private const ALLOWED_FEATURE_LAYERS = [
        'mog:gaza general map',
        'mog:V_BUILDING_GEOSRV',
        'mog:V_CENTERLINE_SEGMENTS',
        'mog:V_LANDMARKS_V2',
    ];

    public function features(string $layers, float $lat, float $lng, int $zoom = 12): Collection
    {
        $allowedLayers = collect(explode(',', $layers))
            ->map(fn (string $layer): string => trim($layer))
            ->filter()
            ->intersect(self::ALLOWED_FEATURE_LAYERS)
            ->values();

        if ($allowedLayers->isEmpty()) {
            return collect();
        }

        $buffer = $this->featureBuffer($zoom);
        $query = http_build_query([
            'service' => 'WMS',
            'version' => '1.1.1',
            'request' => 'GetFeatureInfo',
            'layers' => $allowedLayers->implode(','),
            'query_layers' => $allowedLayers->implode(','),
            'styles' => '',
            'format' => 'image/png',
            'transparent' => 'true',
            'info_format' => 'application/json',
            'feature_count' => 50,
            'srs' => 'EPSG:4326',
            'bbox' => implode(',', [$lng - $buffer, $lat - $buffer, $lng + $buffer, $lat + $buffer]),
            'width' => 101,
            'height' => 101,
            'x' => 50,
            'y' => 50,
        ], '', '&', PHP_QUERY_RFC3986);

        $username = (string) config('geoserver.username');
        $password = (string) config('geoserver.password');
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'Authorization: Basic '.base64_encode($username.':'.$password),
                ]),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $url = rtrim((string) config('geoserver.base_url'), '/').'/wms?'.$query;
        $body = @file_get_contents($url, false, $context);
        $data = $body === false ? null : json_decode($body, true);

        return collect($data['features'] ?? [])
            ->map(function (array $feature): array {
                $properties = $feature['properties'] ?? [];
                $featureId = (string) ($feature['id'] ?? '');
                $id = strrchr($featureId, '.');
                $layer = strstr($featureId, '.', true);

                if (! isset($properties['W4_GENERAL_ID']) && str_contains(strtoupper($featureId), 'WATER_VALVES.') && $id !== false) {
                    $properties['W4_GENERAL_ID'] = (int) substr($id, 1);
                }

                $properties['_feature_layer'] = $layer === false ? null : $layer;
                $properties['_feature_id'] = $featureId;

                return $properties;
            })
            ->filter()
            ->unique('_feature_id')
            ->values();
    }

    private function featureBuffer(int $zoom): float
    {
        $zoom = max(10, min(19, $zoom));

        return max(0.00005, min(0.001, 0.001 / (2 ** max(0, $zoom - 12))));
    }
}
