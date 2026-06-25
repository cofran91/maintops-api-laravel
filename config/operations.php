<?php

return [
    'events' => [
        'stream' => env('OPERATIONS_EVENT_STREAM', 'ops:events'),
    ],

    'realtime' => [
        'token_secret' => env('REALTIME_TOKEN_SECRET'),
        'token_ttl_seconds' => (int) env('REALTIME_TOKEN_TTL_SECONDS', 300),
        'token_audience' => env('REALTIME_TOKEN_AUDIENCE', 'realtime'),
    ],
];
