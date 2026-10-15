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

    'spotify' => [
        'client_id' => env('SPOTIFY_CLIENT_ID'),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET'),
    ],
    'tmdb' => [
        'key' => env('TMDB_API_KEY_LARAVEL'),
    ],
    'exchange_rate' => [
        'url' => env('EXCHANGE_RATE_URL', 'https://open.er-api.com/v6/latest/USD'),
    ],
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash'),
    ],
    'fuseki' => [
        'endpoint' => env('FUSEKI_ENDPOINT', 'http://localhost:3030/film/sparql'),
        'base_uri' => env('FUSEKI_MOVIE_BASE_URI', 'http://pilem.org/cinegraph/resource/movie/'),
        'person_base_uri' => env('FUSEKI_PERSON_BASE_URI', 'http://pilem.org/cinegraph/resource/person/'),
        'genre_base_uri' => env('FUSEKI_GENRE_BASE_URI', 'http://pilem.org/cinegraph/resource/genre/'),
        'country_base_uri' => env('FUSEKI_COUNTRY_BASE_URI', 'http://pilem.org/cinegraph/resource/country/'),
    ],

];
