

<?php

//phpinfo();

$referer = $_SERVER['HTTP_REFERER'] ?? '';

if (str_starts_with($referer, 'http://localhost') ||  str_starts_with($referer, 'https://scada.mogaza.org')) {

    $queryString = http_build_query($_REQUEST);
    $url = 'https://geo.mogaza.org/geoserver' . $_GET['g_page'] . '?' . $queryString;
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
    $result = file_get_contents($url, false, context: $context);

    header("Content-Type: image/png");
    echo $result;

} else {
    echo 'error';
}