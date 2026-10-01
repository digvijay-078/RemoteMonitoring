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

    /*
    |--------------------------------------------------------------------------
    | WebRTC ICE Servers (STUN & TURN)
    |--------------------------------------------------------------------------
    | Configure STUN and TURN (coturn) servers for cross-network NAT traversal.
    */
    'webrtc' => [
        'stun_urls' => array_filter(explode(',', env('WEBRTC_STUN_SERVERS', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302,stun:stun.cloudflare.com:3478,stun:openrelay.metered.ca:80'))),
        'turn_url' => env('WEBRTC_TURN_SERVER', 'turn:openrelay.metered.ca:80,turn:openrelay.metered.ca:443,turn:openrelay.metered.ca:443?transport=tcp'),
        'turn_username' => env('WEBRTC_TURN_USERNAME', 'openrelayproject'),
        'turn_credential' => env('WEBRTC_TURN_CREDENTIAL', 'openrelayproject'),
    ],

];
