<?php

return [
    'providers' => [
        'primary' => env('NOTIFICATION_PRIMARY_PROVIDER', 'twilio'),
        'fallback' => env('NOTIFICATION_FALLBACK_PROVIDER', 'vonage'),
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from_number' => env('TWILIO_FROM_NUMBER'),
        ],
        'vonage' => [
            'api_key' => env('VONAGE_API_KEY'),
            'api_secret' => env('VONAGE_API_SECRET'),
            'from' => env('VONAGE_FROM'),
        ],
        'victorylink' => [
            'username' => env('VICTORYLINK_USERNAME'),
            'password' => env('VICTORYLINK_PASSWORD'),
            'sender' => env('VICTORYLINK_SENDER'),
        ],
    ],
    'rate_limits' => [
        'max_per_hour' => 10,
        'max_per_day' => 50,
    ],
    'retry' => [
        'max_attempts' => 3,
        'delay_seconds' => [60, 300, 900],
    ],
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    ],
];
