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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'google_map_api_key' => env('GOOGLE_MAP_API_KEY'),
    'ipinfo_api_token' => env('IPINFO_API_TOKEN'),
    'ipchecking_api_key' => env('IPCHECKING_API_KEY'),
    'ip2location_api_key' => env('IP2LOCATION_API_KEY'),
    'original_site' => env('ORIGINAL_SITE'),
    'cloudflare_api' => env('CLOUDFLARE_API'),
    'ipapiis_api_key' => env('IPAPIIS_API_KEY'),

];
