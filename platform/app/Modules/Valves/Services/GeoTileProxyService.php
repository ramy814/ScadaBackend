<?php

declare(strict_types=1);

namespace App\Modules\Valves\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Authenticated replacement for the legacy public/wms.php: same two upstream
 * GeoWebCache endpoints and transparent-PNG fallback, but behind platform auth
 * (not a Referer check) and with TLS verification left on.
 */
class GeoTileProxyService
{
    private const UPSTREAM = ['wms' => '/gwc/service/wms', 'wmts' => '/gwc/service/wmts'];

    private const BLANK_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    public function tile(string $service, array $query): Response
    {
        try {
            $upstream = Http::withBasicAuth((string) config('geoserver.username'), (string) config('geoserver.password'))
                ->timeout(15)
                ->get(rtrim((string) config('geoserver.base_url'), '/').self::UPSTREAM[$service], $query);

            if ($upstream->successful() && str_starts_with((string) $upstream->header('Content-Type'), 'image/')) {
                return response($upstream->body(), 200, [
                    'Content-Type' => $upstream->header('Content-Type'),
                    'Cache-Control' => 'private, max-age=86400',
                    'X-WMS-Cache' => (string) $upstream->header('geowebcache-cache-result'),
                ]);
            }
        } catch (Throwable) {
            // fall through to the transparent tile, as the legacy proxy did
        }

        return response(base64_decode(self::BLANK_PNG), 200, ['Content-Type' => 'image/png', 'X-WMS-Proxy-Fallback' => '1']);
    }
}
