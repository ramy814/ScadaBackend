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
    $request = $_REQUEST;
    $geoServerPath = $request['g_page'] ?? '/wms';
    unset($request['g_page']);

    if ($geoServerPath !== '/wms') {
        http_response_code(400);
        exit('Invalid GeoServer path');
    }

    $queryString = http_build_query($request);
    $url = 'https://geo.mogaza.org/geoserver/wms?' . $queryString;
    $auth = base64_encode('mobile_apps:v0ypiw28WS51');

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
