<?php

return [
    'mog_app_name'      => env('MOG_APP_NAME', 'scada'),
    'sso_client_id'     => env('SSO_CLIENT_ID', ''),
    'sso_client_secret' => env('SSO_CLIENT_SECRET', ''),
    'sso_default_guard' => env('SSO_DEFAULT_GUARD', 'admin'),
    'main-disk'         => env('MAIN_DISK', 'local'),
    'oracle_conn_name'  => env('ORACLE_CONN_NAME', 'oracle'),
];
