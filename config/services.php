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
    | a conventional file to locate this type of information.
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

    'factus' => [
        'enabled' => env('FACTUS_ENABLED', false),
        'base_url' => env('FACTUS_BASE_URL', 'https://api-sandbox.factus.com.co'),
        'username' => env('FACTUS_USERNAME'),
        'password' => env('FACTUS_PASSWORD'),
        'client_id' => env('FACTUS_CLIENT_ID'),
        'client_secret' => env('FACTUS_CLIENT_SECRET'),
        'numbering_range_id' => env('FACTUS_NUMBERING_RANGE_ID'),
        'send_email' => env('FACTUS_SEND_EMAIL', false),
    ],

];
