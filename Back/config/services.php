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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'fake_checkout' => (bool) env('STRIPE_FAKE_CHECKOUT', false),
        'success_url' => env('STRIPE_CHECKOUT_SUCCESS_URL', env('APP_URL').'/billing/success'),
        'cancel_url' => env('STRIPE_CHECKOUT_CANCEL_URL', env('APP_URL').'/billing/cancel'),
        'monthly_amount' => 700,
        'yearly_amount' => 9600,
        'currency' => 'USD',
    ],

    'impact' => [
        'amount_per_completed_read' => (float) env('IMPACT_AMOUNT_PER_COMPLETED_READ', 0.07),
        'completed_read_percent' => (int) env('IMPACT_COMPLETED_READ_PERCENT', 80),
        'nonprofit_share_percent' => (int) env('IMPACT_NONPROFIT_SHARE_PERCENT', 33),
        'operations_share_percent' => (int) env('IMPACT_OPERATIONS_SHARE_PERCENT', 33),
        'growth_share_percent' => (int) env('IMPACT_GROWTH_SHARE_PERCENT', 34),
    ],

    'irs' => [
        'verification_mode' => env('IRS_VERIFICATION_MODE', 'imported'),
        'dataset_path' => env('IRS_DATASET_PATH'),
        'eo_bmf_urls' => array_filter(array_map('trim', explode(',', (string) env(
            'IRS_EO_BMF_URLS',
            'https://www.irs.gov/pub/irs-soi/eo1.csv,https://www.irs.gov/pub/irs-soi/eo2.csv,https://www.irs.gov/pub/irs-soi/eo3.csv,https://www.irs.gov/pub/irs-soi/eo4.csv',
        )))),
        'name_match_threshold' => (int) env('IRS_NAME_MATCH_THRESHOLD', 90),
        'allow_local_match' => (bool) env('IRS_ALLOW_LOCAL_MATCH', false),
        'local_organizations' => [],
    ],

];
