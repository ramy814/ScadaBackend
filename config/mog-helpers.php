<?php

return [
    'mog_app_name' => 'scada',
    'sso_client_id' => '0413e081-bb37-4922-b173-e0eadc318c83',
    'sso_client_secret' => '3d3dfe5c-dc4b-49cb-b287-2913832408a0',
    'sso_default_guard' => env('SSO_DEFAULT_GUARD', 'admin'),
    'main-disk' => env('MAIN_DISK', 'local'),
    'oracle_conn_name' => env('ORACLE_CONN_NAME', 'oracle'),
];
