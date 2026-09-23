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

    /*
    |--------------------------------------------------------------------------
    | SMS gateway (generic driver interface, wired in Phase 13)
    |--------------------------------------------------------------------------
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'api_url' => env('SMS_API_URL'),
        'api_key' => env('SMS_API_KEY'),
        'sender_id' => env('SMS_SENDER_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment gateways (wired in Phase 4)
    |--------------------------------------------------------------------------
    */

    'bkash' => [
        'sandbox' => env('BKASH_SANDBOX', true),
        'base_url' => env('BKASH_BASE_URL', 'https://tokenized.sandbox.bka.sh/v1.2.0-beta'),
        'app_key' => env('BKASH_APP_KEY'),
        'app_secret' => env('BKASH_APP_SECRET'),
        'username' => env('BKASH_USERNAME'),
        'password' => env('BKASH_PASSWORD'),
    ],

    'sslcommerz' => [
        'sandbox' => env('SSLCOMMERZ_SANDBOX', true),
        'base_url' => env('SSLCOMMERZ_BASE_URL', 'https://sandbox.sslcommerz.com'),
        'store_id' => env('SSLCOMMERZ_STORE_ID'),
        'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
    ],

    'nagad' => [
        'sandbox' => env('NAGAD_SANDBOX', true),
        'base_url' => env('NAGAD_BASE_URL', 'http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs'),
        'merchant_id' => env('NAGAD_MERCHANT_ID'),
        'merchant_number' => env('NAGAD_MERCHANT_NUMBER'),
        'public_key' => env('NAGAD_PUBLIC_KEY'),
        'private_key' => env('NAGAD_PRIVATE_KEY'),
    ],

];
