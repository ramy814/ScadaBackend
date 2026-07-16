<?php

declare(strict_types=1);

namespace App\Services\Valves;

use Illuminate\Support\Collection;

class ValvesMapService
{
    private const GEOSERVER_OWS_URL = 'http://appdev-01.mog.org:8080/geoserver/cite/ows';

    private const ALLOWED_FEATURE_LAYERS = [
        'cite:WATER_VALVES',
        'cite:WATER_PIPE',
        'cite:V_BUILDING_GEOSRV',
        'cite:V_CENTERLINE_SEGMENTS',
        'cite:V_LANDMARKS_V2',
    ];

    public function settings(string $baseUrl): array
    {
        return [
            'wms_url' => rtrim($baseUrl, '/') . '/wms.php',
            'layers' => [
                ['layer_name' => 'cite:V_BUILDING_GEOSRV', 'name' => 'المباني', 'version' => '1.1.0', 'visible' => false],
                ['layer_name' => 'cite:V_CENTERLINE_SEGMENTS', 'name' => 'محاور الشوارع', 'version' => '1.1.0', 'visible' => true],
                ['layer_name' => 'cite:V_LANDMARKS_V2', 'name' => 'المعالم', 'version' => '1.1.0', 'visible' => true],
                ['layer_name' => 'cite:WATER_PIPE', 'name' => 'شبكة الأنابيب', 'version' => '1.1.0', 'visible' => true],
                ['layer_name' => 'cite:WATER_VALVES', 'name' => 'محابس المياه', 'version' => '1.1.1', 'visible' => true],
            ],
        ];
    }

    public function features(string $layers, float $lat, float $lng): Collection
    {
        $allowedLayers = collect(explode(',', $layers))
            ->map(fn (string $layer): string => trim($layer))
            ->filter()
            ->intersect(self::ALLOWED_FEATURE_LAYERS)
            ->values();

        if ($allowedLayers->isEmpty()) {
            return collect();
        }

        $buffer = 0.001;
        $query = http_build_query([
            'service' => 'WFS',
            'version' => '1.0.0',
            'request' => 'GetFeature',
            'typeName' => $allowedLayers->implode(','),
            'outputFormat' => 'application/json',
            'bbox' => implode(',', [$lng - $buffer, $lat - $buffer, $lng + $buffer, $lat + $buffer]),
        ], '', '&', PHP_QUERY_RFC3986);

        $curl = curl_init(self::GEOSERVER_OWS_URL . '?' . $query);
        if ($curl === false) {
            return collect();
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'geo@2022_mogwinO: admin',
                'Accept: application/json',
            ],
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($body === false || $status < 200 || $status >= 300) {
            return collect();
        }

        $data = json_decode((string) $body, true);

        return collect($data['features'] ?? [])
            ->map(function (array $feature): array {
                $properties = $feature['properties'] ?? [];
                $featureId = (string) ($feature['id'] ?? '');
                $id = strrchr($featureId, '.');

                if (!isset($properties['W4_GENERAL_ID']) && str_contains(strtoupper($featureId), 'WATER_VALVES.') && $id !== false) {
                    $properties['W4_GENERAL_ID'] = (int) substr($id, 1);
                }

                return $properties;
            })
            ->filter()
            ->values();
    }
}
