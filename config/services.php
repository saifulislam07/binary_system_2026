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
    | SMS gateway (member notifications)
    |--------------------------------------------------------------------------
    | driver: log (writes to the log only) | bulksmsbd. Sending also needs
    | NOTIFY_SMS=true (config/notifications.php).
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    'bulksmsbd' => [
        'base_url' => env('BULKSMSBD_BASE_URL', 'https://bulksmsbd.net/api'),
        'api_key' => env('BULKSMSBD_API_KEY'),
        'sender_id' => env('BULKSMSBD_SENDER_ID'),
        'type' => env('BULKSMSBD_TYPE', 'text'),
        'timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp gateway (member notifications)
    |--------------------------------------------------------------------------
    | driver: log (writes to the log only) | meta (WhatsApp Business Cloud
    | API). Sending also needs NOTIFY_WHATSAPP=true. The template must be an
    | approved utility template whose body has {{1}} (title) and {{2}}
    | (message), in every language listed under `languages`.
    */

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'meta' => [
            'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com'),
            'version' => env('WHATSAPP_GRAPH_VERSION', 'v26.0'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'template' => env('WHATSAPP_TEMPLATE', 'account_update'),
            // App locale => template language code.
            'languages' => [
                'bn' => env('WHATSAPP_TEMPLATE_LANG_BN', 'bn'),
                'en' => env('WHATSAPP_TEMPLATE_LANG_EN', 'en'),
            ],
            'timeout' => 10,
        ],
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
