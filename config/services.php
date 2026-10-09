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
        // The browser uses a Services ID; Apple binds web authorization codes to it.
        'web_client_id' => env('APPLE_WEB_CLIENT_ID', 'io.codepassion.wildforce-web'),
        'web_redirect_uri' => env('APPLE_WEB_REDIRECT_URI'),
        'team_id' => env('APPLE_TEAM_ID', 'SV3ZXK4PZF'), // codepassion
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
    ],

    'google' => [
        // https://console.cloud.google.com/apis/credentials?project=wildforce-prod
        'client_id' => env('GOOGLE_CLIENT_ID'),
        // Mobile clients can keep using GOOGLE_CLIENT_ID. The browser needs its
        // own OAuth client because Google binds ID tokens to the client that
        // requested them.
        'web_client_id' => env('GOOGLE_WEB_CLIENT_ID'),
    ],

    'google_play' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME', 'io.codepassion.wildforce.android'),
        // Keep this JSON service-account credential in the deployment secret
        // store. It must never be bundled into the Android application.
        'service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON'),
        'product_plans' => [
            'io.codepassion.wildforce.premium' => [
                'plan' => 'premium', 'billing_interval' => 'month', 'billing_interval_count' => 1,
            ],
            'io.codepassion.wildforce.premium.year' => [
                'plan' => 'premium', 'billing_interval' => 'year', 'billing_interval_count' => 1,
            ],
        ],
    ],

    'app_store' => [
        'bundle_id' => env('APP_STORE_BUNDLE_ID', 'io.codepassion.doublegym'),
        'root_certificate_fingerprints' => [
            // Apple Root CA - G3, published by Apple at apple.com/certificateauthority.
            '63343abfb89a6a03ebb57e9b3f5fa7be7c4f5c756f3017b3a8c488c3653e9179',
        ],
        'product_plans' => [
            'io.codepassion.wildforce.subscription.standard' => [
                'plan' => 'premium',
                'billing_interval' => 'month',
                'billing_interval_count' => 1,
            ],
            'io.codepassion.wildforce.subscription.year' => [
                'plan' => 'premium',
                'billing_interval' => 'year',
                'billing_interval_count' => 1,
            ],
        ],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'prices' => [
            'friends' => [
                'monthly' => [
                    'price_id' => env('STRIPE_FRIEND_MONTHLY_PRICE_ID', 'price_1UKwmiFI3Ev7T3CmXupSr5Zh'),
                ],
                'yearly' => [
                    'price_id' => env('STRIPE_FRIEND_YEARLY_PRICE_ID', 'price_1UKwEFFI3Ev7T3CmXwt2wQyG'),
                ],
            ],
            'premium' => [
                'monthly' => [
                    'price_id' => env('STRIPE_PREMIUM_MONTHLY_PRICE_ID'),
                ],
                'yearly' => [
                    'price_id' => env('STRIPE_PREMIUM_YEARLY_PRICE_ID'),
                ],
            ],
            'coach_basic' => [
                'monthly' => [
                    'price_id' => env('STRIPE_COACH_BASIC_MONTHLY_PRICE_ID'),
                ],
                'yearly' => [
                    'price_id' => env('STRIPE_COACH_BASIC_YEARLY_PRICE_ID'),
                ],
            ],
            'coach_studio' => [
                'monthly' => [
                    'price_id' => env('STRIPE_COACH_STUDIO_MONTHLY_PRICE_ID'),
                ],
                'yearly' => [
                    'price_id' => env('STRIPE_COACH_STUDIO_YEARLY_PRICE_ID'),
                ],
            ],
            'coach_pro' => [
                'monthly' => [
                    'price_id' => env('STRIPE_COACH_PRO_MONTHLY_PRICE_ID'),
                ],
                'yearly' => [
                    'price_id' => env('STRIPE_COACH_PRO_YEARLY_PRICE_ID'),
                ],
            ],
        ],
    ],

    'feedback' => [
        'recipient' => env('FEEDBACK_MAIL_TO', 'hello@codepassion.io'),
    ],

    'open_food_facts' => [
        'base_url' => env('OPEN_FOOD_FACTS_BASE_URL', 'https://world.openfoodfacts.org'),
        'user_id' => env('OPEN_FOOD_FACTS_USER_ID'),
        'password' => env('OPEN_FOOD_FACTS_PASSWORD'),
        'user_agent' => env('OPEN_FOOD_FACTS_USER_AGENT', 'Wildforce/1.0 (https://wildforce.app)'),
        'app_name' => env('OPEN_FOOD_FACTS_APP_NAME', 'Wildforce'),
        'app_version' => env('OPEN_FOOD_FACTS_APP_VERSION', '1.0'),
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
