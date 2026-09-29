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

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID', 'io.codepassion.doublegym'),
        'team_id' => env('APPLE_TEAM_ID', 'SV3ZXK4PZF'), // codepassion
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    'app_store' => [
        'bundle_id' => env('APP_STORE_BUNDLE_ID', 'io.codepassion.doublegym'),
        'root_certificate_fingerprints' => [
            // Apple Root CA - G3, published by Apple at apple.com/certificateauthority.
            '63343abfb89a6a03ebb57e9b3f5fa7be7c4f5c756f3017b3a8c488c3653e9179',
        ],
        'product_plans' => [
            'io.codepassion.wildforce.subscription.standard' => 'member_monthly',
            'io.codepassion.wildforce.subscription.year' => 'member_yearly',
        ],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'friend_prices' => [
            'monthly' => [
                'price_id' => env('STRIPE_FRIEND_MONTHLY_PRICE_ID', 'price_1UKwmiFI3Ev7T3CmXupSr5Zh'),
                'plan' => 'member_monthly',
            ],
            'yearly' => [
                'price_id' => env('STRIPE_FRIEND_YEARLY_PRICE_ID', 'price_1UKwEFFI3Ev7T3CmXwt2wQyG'),
                'plan' => 'member_yearly',
            ],
        ],
    ],

    'feedback' => [
        'recipient' => env('FEEDBACK_MAIL_TO', 'hello@codepassion.io'),
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

];
