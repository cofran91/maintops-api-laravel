<?php

return [
    'events' => [
        'stream' => env('OPERATIONS_EVENT_STREAM', 'ops:events'),
    ],

    'service_tokens' => [
        'issuer' => env('SERVICE_TOKEN_ISSUER', env('APP_URL')),
        'secret' => env('SERVICE_TOKEN_SECRET'),
        'ttl_seconds' => (int) env('SERVICE_TOKEN_TTL_SECONDS', 300),
    ],

    'analytics' => [
        'service_key' => env('OPERATIONS_ANALYTICS_SERVICE_KEY'),
    ],
];
