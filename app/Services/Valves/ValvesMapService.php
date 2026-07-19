<?php

declare(strict_types=1);

namespace App\Services\Valves;

use Illuminate\Support\Collection;

class ValvesMapService
{
    private const GEOSERVER_OWS_URLS = [
        'http://appdev-01:8080/geoserver/cite/ows',
        'http://appdev-01.mog.org:8080/geoserver/cite/ows',
    ];

    private const ALLOWED_FEATURE_LAYERS = [
        'cite:WATER_VALVES',
        'cite:WATER_PIPE',
        'cite:V_BUILDING_GEOSRV',
        'cite:V_CENTERLINE_SEGMENTS',
        'cite:V_LANDMARKS_V2',
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
            'service' => 'WFS',
            'version' => '1.0.0',
            'request' => 'GetFeature',
            'typeName' => $allowedLayers->implode(','),
            'outputFormat' => 'application/json',
            'bbox' => implode(',', [$lng - $buffer, $lat - $buffer, $lng + $buffer, $lat + $buffer]),
        ], '', '&', PHP_QUERY_RFC3986);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $data = null;

        foreach (self::GEOSERVER_OWS_URLS as $url) {
            $body = @file_get_contents($url . '?' . $query, false, $context);
            $decoded = $body === false ? null : json_decode($body, true);

            if (is_array($decoded) && array_key_exists('features', $decoded)) {
                $data = $decoded;
                break;
            }
        }

        return collect($data['features'] ?? [])
            ->map(function (array $feature): array {
                $properties = $feature['properties'] ?? [];
                $featureId = (string) ($feature['id'] ?? '');
                $id = strrchr($featureId, '.');
                $layer = strstr($featureId, '.', true);

                if (!isset($properties['W4_GENERAL_ID']) && str_contains(strtoupper($featureId), 'WATER_VALVES.') && $id !== false) {
                    $properties['W4_GENERAL_ID'] = (int) substr($id, 1);
                }

                $properties['_feature_layer'] = $layer === false ? null : $layer;
                $properties['_feature_id'] = $featureId;

                return $properties;
            })
            ->filter()
            ->values();
    }

    private function featureBuffer(int $zoom): float
    {
        $zoom = max(10, min(19, $zoom));

        return max(0.00005, min(0.001, 0.001 / (2 ** max(0, $zoom - 12))));
    }
}
