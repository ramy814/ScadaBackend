<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Driver app SMS login. No SMS provider is integrated yet (same as legacy).
    // Owner decision (2026-11-03): keep the fixed code 000000 until a provider exists.
    // Set SMS_TEST_CODE to another value to change it, or to "disabled" to refuse fixed codes.
    'sms' => [
        'test_code' => env('SMS_TEST_CODE', '000000') === 'disabled' ? null : env('SMS_TEST_CODE', '000000'),
    ],

    // Map tile clients (legacy MapClientAuthService); same public endpoints as legacy.
    'signal' => [
        'map_auth_url' => env('SIGNAL_MAP_AUTH_URL', 'https://mapps.mogaza.org/api/get_signal_statistics'),
    ],

    'hiraf' => [
        'profile_url' => env('HIRAF_PROFILE_URL', 'https://apps.mogaza.org/api/account-follow-up/profile'),
    ],

];
