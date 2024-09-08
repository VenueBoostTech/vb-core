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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'stripe' => [
        'key' => env('APP_ENV') === 'production' ? env('STRIPE_KEY_LIVE') : env('STRIPE_KEY_TEST'),
        'admin_redirect_url' => env('APP_ENV') === 'production' ? env('STRIPE_ADMIN_URL_LIVE') : env('STRIPE_ADMIN_URL_TEST'),
        'web_redirect_url' => env('APP_ENV') === 'production' ? env('STRIPE_WEB_URL_LIVE') : env('STRIPE_WEB_URL_TEST'),
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],
    'exchange_rate_api' => [
        'key' => env('EXCHANGE_RATE_API_KEY'),
    ],
    '2checkout' => [
        'seller_id' => env('TWOCHECKOUT_SELLER_ID'),
        'secret_key' => env('TWOCHECKOUT_SECRET_KEY'),
        'private_key' => env('TWOCHECKOUT_PRIVATE_KEY'),
        'sandbox' => env('TWOCHECKOUT_SANDBOX', true),
    ],
];
