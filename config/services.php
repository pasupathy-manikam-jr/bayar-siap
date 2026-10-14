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

    // Stripe Price id of the Solo plan (RM 49/month, created in the Stripe dashboard).
    'stripe' => [
        'solo_price' => env('STRIPE_PRICE_SOLO'),
    ],

    // Client texts (OTP codes, links, reminders). driver: log | twilio. channel: sms | whatsapp.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '60'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
        'channel' => env('TWILIO_CHANNEL', 'sms'),
    ],

    // Firebase Cloud Messaging (push to the contractor app). Path to the service-account JSON from the
    // Firebase console; unset = pushes are written to the log instead.
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),
    ],

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

];
