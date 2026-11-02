<?php

// Server-side only; never sent to the browser. No credential defaults are committed.
return [
    'base_url' => env('GEOSERVER_BASE_URL', 'https://geo.mogaza.org/geoserver'),
    'username' => env('GEOSERVER_USERNAME'),
    'password' => env('GEOSERVER_PASSWORD'),
];
