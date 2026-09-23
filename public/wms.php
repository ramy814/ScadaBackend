<?php

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$refererHost = parse_url($referer, PHP_URL_HOST);
$allowedRefererHosts = [
    'localhost',
    '127.0.0.1',
    'scada.mogaza.org',
];
$isAllowedReferer = is_string($refererHost)
    && in_array(strtolower($refererHost), $allowedRefererHosts, true);

if ($isAllowedReferer) {
    $geoServer = require dirname(__DIR__) . '/config/geoserver.php';
    $request = $_REQUEST;
    $geoServerPath = $request['g_page'] ?? '/wms';
    unset($request['g_page']);

    $upstreamPaths = [
        '/wms' => '/gwc/service/wms',
        '/wmts' => '/gwc/service/wmts',
    ];

    if (! isset($upstreamPaths[$geoServerPath])) {
        http_response_code(400);
        exit('Invalid GeoServer path');
    }

    $queryString = http_build_query($request);
    $url = rtrim($geoServer['base_url'], '/') . $upstreamPaths[$geoServerPath] . '?' . $queryString;
    $auth = base64_encode($geoServer['username'] . ':' . $geoServer['password']);

    //stream method
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Authorization: Basic $auth\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    $result = @file_get_contents($url, false, context: $context);

    header('Content-Type: image/png');

    foreach ($http_response_header ?? [] as $upstreamHeader) {
        if (stripos($upstreamHeader, 'geowebcache-cache-result:') === 0) {
            header('X-WMS-Cache: ' . trim(substr($upstreamHeader, strlen('geowebcache-cache-result:'))));
            break;
        }
    }

    if ($result === false) {
        header('X-WMS-Proxy-Fallback: 1');
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        exit;
    }

    echo $result;

} else {
    http_response_code(403);
    echo 'error';
}
