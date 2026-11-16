<?php

return [

    /*
    | الـ Driver الافتراضي للبث. بمشروعنا reverb (WebSocket من Laravel).
    | بالتيست نخليه null (انظري phpunit.xml) حتى ما يحاول الاتصال بسيرفر حقيقي.
    */
    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        // Laravel يتصل بـ Reverb عبر HTTP ليسلّمه الحدث، وReverb يدفعه للمتصفحات عبر WebSocket
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle options
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
