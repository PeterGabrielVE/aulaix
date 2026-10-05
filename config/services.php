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
    | AI Service (decoupled module)
    |--------------------------------------------------------------------------
    |
    | Base URL of the standalone AI microservice (see docker/ai-service).
    | Laravel never talks to AI logic directly — it only ever goes through
    | App\Contracts\AIServiceClient, so the implementation (this HTTP
    | service today) can be swapped without touching calling code.
    |
    */

    'ai' => [
        'base_url' => env('AI_SERVICE_URL', 'http://ai-service:8000'),
        'timeout' => (int) env('AI_SERVICE_TIMEOUT', 5),
    ],

];
