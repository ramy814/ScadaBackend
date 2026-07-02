<?php
declare(strict_types=1);

/**
 * GeoServer WMS proxy for the valves map.
 *
 * Keep this as a plain PHP public proxy because the required GeoServer header
 * name contains "@" and Laravel Http rejects it. Do not add local disk cache;
 * GeoServer/server-side caching is expected upstream.
 */

// Allow GeoServer to take as long as needed (it can be slow without tile caching)
set_time_limit(0);
ignore_user_abort(true);

const GEOSERVER_BASE = 'http://appdev-01.mog.org:8080/geoserver';
const GEOSERVER_HEADER = 'geo@2022_mogwinO: admin';
const TRANSPARENT_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
const GETMAP_ATTEMPTS = 1;
const CONNECT_TIMEOUT_SECONDS = 5;
const REQUEST_TIMEOUT_SECONDS = 90;
const MAX_GETMAP_DIMENSION = 256;

const ALLOWED_LAYERS = [
    'cite:WATER_VALVES',
    'cite:WATER_PIPE',
    'cite:V_BUILDING_GEOSRV',
    'cite:V_CENTERLINE_SEGMENTS',
    // 'cite:V_LANDMARKS_V3',
];

function noStoreHeaders(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

function sendText(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    noStoreHeaders();
    echo $message;
    exit;
}

function sendJson(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    noStoreHeaders();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendTransparent(string $reason): never
{
    http_response_code(200);
    header('X-WMS-Proxy-Fallback: transparent');
    header('X-WMS-Proxy-Reason: ' . substr($reason, 0, 160));
    header('Content-Type: image/png');
    noStoreHeaders();
    echo base64_decode(TRANSPARENT_PNG);
    exit;
}

function sendNoContent(string $reason): never
{
    http_response_code(204);
    header('X-WMS-Proxy-Fallback: no-content');
    header('X-WMS-Proxy-Reason: ' . substr($reason, 0, 160));
    header_remove('Content-Type');
    noStoreHeaders();
    exit;
}

function requestValue(string $name, string $default = ''): string
{
    return (string)($_GET[$name] ?? $_GET[strtoupper($name)] ?? $default);
}

function splitLayers(string $layers): array
{
    return array_values(array_filter(array_map('trim', explode(',', $layers))));
}

function assertAllowedLayers(array $layers): void
{
    if ($layers === []) {
        sendText(400, 'Missing WMS layers parameter.');
    }

    foreach ($layers as $layer) {
        if (!in_array($layer, ALLOWED_LAYERS, true)) {
            sendText(403, 'WMS layer is not allowed: ' . $layer);
        }
    }
}

function geoServerEndpoint(array $layers): string
{
    $allCiteLayers = array_reduce(
        $layers,
        fn (bool $carry, string $layer): bool => $carry && str_starts_with($layer, 'cite:'),
        true
    );

    return $allCiteLayers ? GEOSERVER_BASE . '/cite/wms' : GEOSERVER_BASE . '/wms';
}

function normalizedQuery(): array
{
    $query = $_GET;

    foreach (['service', 'request', 'version', 'layers', 'styles', 'format', 'transparent', 'width', 'height', 'srs', 'bbox'] as $key) {
        $upper = strtoupper($key);
        if (!isset($query[$key]) && isset($query[$upper])) {
            $query[$key] = $query[$upper];
            unset($query[$upper]);
        }
    }

    $query['service'] = 'WMS';
    $query['request'] = requestValue('request');

    return $query;
}

function requestInt(string $name): int
{
    return (int) requestValue($name, '0');
}

function isOversizedGetMap(): bool
{
    return requestInt('width') > MAX_GETMAP_DIMENSION
        || requestInt('height') > MAX_GETMAP_DIMENSION;
}

function proxyUrl(string $endpoint, array $query): string
{
    return $endpoint . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function fetchGeoServer(
    string $url,
    int $connectTimeout = CONNECT_TIMEOUT_SECONDS,
    int $requestTimeout = REQUEST_TIMEOUT_SECONDS
): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        return [
            'ok' => false,
            'status' => 0,
            'content_type' => 'text/plain; charset=UTF-8',
            'body' => '',
            'error' => 'Unable to initialize cURL.',
        ];
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT => $requestTimeout,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            GEOSERVER_HEADER,
            'Accept: image/png,application/json,text/html,text/plain,*/*',
            'Connection: close',
        ],
    ]);

    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $contentType = (string)(curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream');
    $error = curl_error($curl);
    curl_close($curl);

    return [
        'ok' => $body !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'content_type' => $contentType,
        'body' => $body === false ? '' : (string)$body,
        'error' => $error,
    ];
}

function hasGeoServerException(string $body): bool
{
    return str_contains($body, 'ServiceException')
        || str_contains($body, 'ExceptionReport')
        || str_contains($body, '<ows:Exception')
        || str_contains($body, '<Exception');
}

function isTileOutOfRange(string $body): bool
{
    return str_contains($body, 'TileOutOfRange');
}

function shouldRetry(array $response): bool
{
    return $response['body'] === ''
        || $response['status'] === 0
        || $response['status'] >= 500;
}

function fetchWithRetry(string $url, int $attempts): array
{
    $response = [
        'ok' => false,
        'status' => 0,
        'content_type' => 'text/plain; charset=UTF-8',
        'body' => '',
        'error' => 'No request attempted.',
    ];

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $response = fetchGeoServer($url);

        if (!shouldRetry($response)) {
            return $response + ['attempts' => $attempt];
        }

        if ($attempt < $attempts) {
            usleep(200000 * $attempt);
        }
    }

    return $response + ['attempts' => $attempts];
}

function sendGeoResponse(array $response): never
{
    http_response_code((int)$response['status']);
    header('Content-Type: ' . $response['content_type']);
    header('X-WMS-Proxy-Upstream-Status: ' . $response['status']);
    header('X-WMS-Proxy-Attempts: ' . ($response['attempts'] ?? 1));
    noStoreHeaders();
    echo $response['body'];
    exit;
}

function handleHealthCheck(): never
{
    $url = GEOSERVER_BASE . '/cite/wms?' . http_build_query([
        'service' => 'WMS',
        'request' => 'GetCapabilities',
    ], '', '&', PHP_QUERY_RFC3986);

    $response = fetchGeoServer($url, 3, 8);
    $healthy = $response['ok']
        && $response['body'] !== ''
        && !hasGeoServerException((string)$response['body']);

    sendJson(200, [
        'ok' => $healthy,
        'status' => $response['status'],
        'message' => $healthy
            ? 'GeoServer is reachable.'
            : 'تعذر الاتصال بخادم الخرائط GeoServer.',
        'error' => $response['error'],
    ]);
}

if (requestValue('health') === '1') {
    handleHealthCheck();
}

$request = strtolower(requestValue('request'));
if (!in_array($request, ['getmap', 'getfeatureinfo'], true)) {
    sendText(400, 'Unsupported WMS request.');
}

if ($request === 'getmap' && isOversizedGetMap()) {
    sendTransparent('Oversized GetMap request blocked');
}

$layers = splitLayers(requestValue('layers'));
assertAllowedLayers($layers);

$query = normalizedQuery();
$url = proxyUrl(geoServerEndpoint($layers), $query);
$attempts = $request === 'getmap' ? GETMAP_ATTEMPTS : 1;
$response = fetchWithRetry($url, $attempts);
$body = (string)$response['body'];

if ($request === 'getmap') {
    if ($body === '' || $response['status'] === 0) {
        sendTransparent('GeoServer unreachable: ' . ($response['error'] ?: 'empty response'));
    }

    if (isTileOutOfRange($body)) {
        sendTransparent('GeoServer TileOutOfRange');
    }

    if (!$response['ok'] || hasGeoServerException($body)) {
        sendTransparent('GeoServer GetMap failed. Status: ' . $response['status'] . '. ' . $response['error']);
    }

    sendGeoResponse($response);
}

if ($body === '' || $response['status'] === 0 || !$response['ok'] || hasGeoServerException($body)) {
    sendNoContent('GeoServer GetFeatureInfo unavailable');
}

sendGeoResponse($response);
