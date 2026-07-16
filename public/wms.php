<?php

//phpinfo();

$referer = $_SERVER['HTTP_REFERER'] ?? '';

//var_dump(extension_loaded('curl'), function_exists('curl_init'));

if (str_starts_with($referer, 'https://projects.mogaza.org')) {
//if (str_starts_with($referer, 'http://localhost:8000')) {

    $queryString = http_build_query($_REQUEST);
    $url = 'http://appdev-01:8080/geoserver' . $_GET['g_page'] . '?' . $queryString;

    //stream method
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Accept: application/json'
        ]
    ]);
    $result = file_get_contents($url, false, context: $context);

    echo $result;

} else {
    echo 'error';
}