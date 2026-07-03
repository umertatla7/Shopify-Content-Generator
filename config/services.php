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

    'ai' => [
        'provider' => env('AI_PROVIDER', env('OPENAI_API_KEY') ? 'openai' : 'stub'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'timeout' => env('OPENAI_TIMEOUT', 60),
        'long_task_timeout' => env('OPENAI_LONG_TASK_TIMEOUT', 180),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 6000),
        'default_input_per_million' => env('OPENAI_INPUT_COST_PER_1M', 0.40),
        'default_cached_input_per_million' => env('OPENAI_CACHED_INPUT_COST_PER_1M', 0.10),
        'default_output_per_million' => env('OPENAI_OUTPUT_COST_PER_1M', 1.60),
        'pricing' => [
            'gpt-4.1' => ['input_per_million' => 2.00, 'cached_input_per_million' => 0.50, 'output_per_million' => 8.00],
            'gpt-4.1-mini' => ['input_per_million' => 0.40, 'cached_input_per_million' => 0.10, 'output_per_million' => 1.60],
            'gpt-4.1-nano' => ['input_per_million' => 0.10, 'cached_input_per_million' => 0.025, 'output_per_million' => 0.40],
            'gpt-4o' => ['input_per_million' => 2.50, 'cached_input_per_million' => 1.25, 'output_per_million' => 10.00],
            'gpt-4o-mini' => ['input_per_million' => 0.15, 'cached_input_per_million' => 0.075, 'output_per_million' => 0.60],
            'gpt-5.4' => ['input_per_million' => 2.50, 'cached_input_per_million' => 0.25, 'output_per_million' => 15.00],
            'gpt-5.4-mini' => ['input_per_million' => 0.75, 'cached_input_per_million' => 0.075, 'output_per_million' => 4.50],
            'gpt-5.4-nano' => ['input_per_million' => 0.20, 'cached_input_per_million' => 0.02, 'output_per_million' => 1.25],
            'gpt-5.5' => ['input_per_million' => 5.00, 'cached_input_per_million' => 0.50, 'output_per_million' => 30.00],
        ],
    ],

    'shopify' => [
        'api_version' => env('SHOPIFY_API_VERSION', '2026-04'),
        'timeout' => env('SHOPIFY_TIMEOUT', 30),
        'default_blog_id' => env('SHOPIFY_DEFAULT_BLOG_ID'),
        'sync_via_queue' => env('SHOPIFY_SYNC_VIA_QUEUE', false),
        'public_app_api_key' => env('SHOPIFY_PUBLIC_APP_API_KEY'),
        'public_app_client_secret' => env('SHOPIFY_PUBLIC_APP_CLIENT_SECRET'),
        'public_app_url' => env('SHOPIFY_PUBLIC_APP_URL', env('APP_URL')),
        'public_app_scopes' => array_values(array_filter(array_map('trim', explode(',', (string) env('SHOPIFY_PUBLIC_APP_SCOPES', 'read_products,write_products,read_content,write_content'))))),
        'public_app_redirect_uri' => env('SHOPIFY_PUBLIC_APP_REDIRECT_URI'),
        'billing_test_mode' => env('SHOPIFY_BILLING_TEST_MODE', env('APP_ENV') !== 'production'),
        'manual_connection_mode' => env('SHOPIFY_MANUAL_CONNECTION_MODE', true),
    ],

    'store_analysis' => [
        'via_queue' => env('STORE_ANALYSIS_VIA_QUEUE', false),
    ],

    'pagespeed' => [
        'enabled' => env('PAGESPEED_INSIGHTS_ENABLED', true),
        'api_key' => env('PAGESPEED_INSIGHTS_API_KEY'),
        'strategy' => env('PAGESPEED_INSIGHTS_STRATEGY', 'mobile'),
        'timeout' => env('PAGESPEED_INSIGHTS_TIMEOUT', 45),
    ],

    'google_search_console' => [
        'client_id' => env('GOOGLE_SEARCH_CONSOLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_SEARCH_CONSOLE_REDIRECT_URI') ?: rtrim((string) env('APP_URL', 'http://localhost'), '/').'/search-console/callback',
        'auth_url' => env('GOOGLE_SEARCH_CONSOLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
        'token_url' => env('GOOGLE_SEARCH_CONSOLE_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'api_url' => env('GOOGLE_SEARCH_CONSOLE_API_URL', 'https://www.googleapis.com/webmasters/v3'),
        'userinfo_url' => env('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo'),
        'timeout' => env('GOOGLE_SEARCH_CONSOLE_TIMEOUT', 45),
    ],

];
