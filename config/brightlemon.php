<?php

return [
    'demo_otp' => env('BRIGHT_LEMON_DEMO_OTP', '123456'),

    'default_branch' => env('BRIGHT_LEMON_DEFAULT_BRANCH', 'Branch drop-off pending'),

    'ems' => [
        'enabled' => filter_var(env('BRIGHT_LEMON_EMS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'mode' => env('ISRAEL_POST_SDK_MODE', 'test'),
        'api_url' => env('ISRAEL_POST_SDK_MODE', 'test') === 'live'
            ? 'https://apimftprd.israelpost.co.il'
            : 'https://apimfttst.israelpost.co.il',
        'subscription_key' => env('ISRAEL_POST_SDK_AUTH_INTERNATIONAL_SUBSCRIPTION_KEY_METHODS'),
        'username' => env('ISRAEL_POST_SDK_AUTH_USERNAME'),
        'password' => env('ISRAEL_POST_SDK_AUTH_PASSWORD'),
        'partner_code' => env('ISRAEL_POST_SDK_SETTINGS_INTL_PARTNER_CODE', '400327'),
        'delivery_type_code' => env('ISRAEL_POST_SDK_INTERNATIONAL_SHIPMENT_SETTINGS_DELIVERY_TYPE_CODE', 10),
        'shipment_type' => env('ISRAEL_POST_SDK_INTERNATIONAL_SHIPMENT_SETTINGS_SHIPMENT_TYPE', '10'),
        'currency' => env('BRIGHT_LEMON_EMS_CURRENCY', 'USD'),
        'timeout' => env('BRIGHT_LEMON_EMS_TIMEOUT', 30),
        'default_recipient_postal_code' => env('BRIGHT_LEMON_EMS_DEFAULT_RECIPIENT_POSTAL_CODE', '00000'),
        'allowed_warning_codes' => array_filter(array_map(
            'trim',
            explode(',', env('BRIGHT_LEMON_EMS_ALLOWED_WARNING_CODES', '201'))
        )),
        'sender' => [
            'name' => env('BRIGHT_LEMON_EMS_SENDER_NAME'),
            'address_line_1' => env('BRIGHT_LEMON_EMS_SENDER_ADDRESS_LINE_1'),
            'address_line_2' => env('BRIGHT_LEMON_EMS_SENDER_ADDRESS_LINE_2'),
            'city' => env('BRIGHT_LEMON_EMS_SENDER_CITY'),
            'postal_code' => env('BRIGHT_LEMON_EMS_SENDER_POSTAL_CODE'),
            'country_code' => env('BRIGHT_LEMON_EMS_SENDER_COUNTRY_CODE', 'IL'),
            'phone' => env('BRIGHT_LEMON_EMS_SENDER_PHONE'),
            'email' => env('BRIGHT_LEMON_EMS_SENDER_EMAIL'),
        ],
        'country_codes' => [
            'UNITED STATES' => 'US',
            'UNITED KINGDOM' => 'GB',
            'GERMANY' => 'DE',
            'FRANCE' => 'FR',
            'CANADA' => 'CA',
            'AUSTRALIA' => 'AU',
            'ISRAEL' => 'IL',
            'SPAIN' => 'ES',
            'ITALY' => 'IT',
            'NETHERLANDS' => 'NL',
            'BELGIUM' => 'BE',
            'SWITZERLAND' => 'CH',
            'JAPAN' => 'JP',
            'SOUTH KOREA' => 'KR',
            'BRAZIL' => 'BR',
            'INDIA' => 'IN',
        ],
    ],
];
