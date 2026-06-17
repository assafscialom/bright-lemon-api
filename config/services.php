<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    // Simasti SMS gateway (https://my.simasti.com) — replaces Twilio. When
    // `enabled` is false (or creds missing) the OTP flow falls back to the demo
    // code so local dev keeps working.
    'simasti' => [
        'enabled' => filter_var(env('SIMASTI_ENABLED', false), FILTER_VALIDATE_BOOL),
        'base_url' => env('SIMASTI_BASE_URL', 'https://my.simasti.com'),
        'login' => env('SIMASTI_LOGIN'),
        'password' => env('SIMASTI_PASSWORD'),
        'sender' => env('SIMASTI_SENDER'),
        'expire_hours' => (int) env('SIMASTI_EXPIRE_HOURS', 24),
        'timeout' => (int) env('SIMASTI_TIMEOUT', 30),
    ],

];
