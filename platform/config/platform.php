<?php

return [
    // Local development identity for /dev-login. Never hard-code a real person here.
    'local_admin_user_id' => (int) env('MOG_LOCAL_ADMIN_USER_ID', 0),

    // SSO user IDs allowed to manage access and the global operational switch.
    'permissions_admin_user_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('MOG_PERMISSIONS_ADMIN_USER_IDS', '')),
    ))),

    'locales' => ['ar', 'en'],
];
