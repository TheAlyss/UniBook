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

    'google_books' => [
        'key' => env('GOOGLE_BOOKS_API_KEY'),
    ],

    'ocr_space' => [
        // "helloworld" is OCR.space's shared demo key (rate limited). Get a free personal key at https://ocr.space/ocrapi/freekey
        'key' => env('OCR_SPACE_API_KEY', 'helloworld'),
    ],

    'openrouter' => [
        // AI only RANKS the books found by Google Books / Open Library. It never writes book details.
        'key' => env('OPENROUTER_API_KEY'),
        'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1/chat/completions'),
        // Free models approved by the team, tried in this order (comma separated).
        'models' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'OPENROUTER_MODELS',
            'baidu/ernie-5.5-flash-preview:free,nvidia/nemotron-3.5-super-vl:free,google/gemma-4-31b-it:free'
        ))))),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 20),
    ],

    'osm' => [
        // Nominatim and OSRM ask clients to identify themselves.
        'user_agent' => env('OSM_USER_AGENT', 'UniBook/1.0 (ICT651 student project)'),
    ],

    'nominatim' => [
        'url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
    ],

    'osrm' => [
        'url' => env('OSRM_URL', 'https://router.project-osrm.org'),
    ],

    'open_library' => [
        // Open Library asks clients to identify themselves.
        'user_agent' => env('OPEN_LIBRARY_USER_AGENT', 'UniBook/1.0 (ICT651 student project)'),
    ],

];
