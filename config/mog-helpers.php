<?php

return [
    'mog_app_name'      => env('MOG_APP_NAME', 'scada'),
    'sso_client_id'     => env('SSO_CLIENT_ID', '0413e081-bb37-4922-b173-e0eadc318c83'),
    'sso_client_secret' => env('SSO_CLIENT_SECRET', '3d3dfe5c-dc4b-49cb-b287-2913832408a0'),
    'sso_default_guard' => env('SSO_DEFAULT_GUARD', 'admin'),
    'main-disk'         => env('MAIN_DISK', 'local'),
    'oracle_conn_name'  => env('ORACLE_CONN_NAME', 'oracle'),

    // The column name in the SSO users table that holds the employee number.
    // Used by EnsureAdminIsAllowlisted middleware to match against FLEET_ADMIN_ALLOWLIST.
    'sso_emp_column'    => env('MOG_SSO_EMP_COLUMN', 'emp_id'),

    // User IDs allowed to view the system logs at /mog/logs
    'logs_allowed_user_ids' => array_filter(array_map('intval', explode(',', env('MOG_LOGS_ALLOWED_USER_IDS', '1061')))),

    // Local development SSO user id. Override in .env; do not hard-code a real person's identity in code comments.
    'local_admin_user_id' => (int) env('MOG_LOCAL_ADMIN_USER_ID', 1061),

    // SSO user IDs allowed to manage portal system permissions.
    'permissions_admin_user_ids' => array_filter(array_map('intval', explode(',', env(
        'MOG_PERMISSIONS_ADMIN_USER_IDS',
        env('MOG_LOGS_ALLOWED_USER_IDS', '1061')
    )))),
];
