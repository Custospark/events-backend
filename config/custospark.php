<?php

return [
    'api_url' => env('CUSTOSPARK_API_URL', 'https://api.custospark.com'),
    'api_key' => env('CUSTOSPARK_API_KEY'),
    'webhook_secret' => env('CUSTOSPARK_WEBHOOK_SECRET'),
    'commission_percent' => (float) env('CUSTOSPARK_COMMISSION_PERCENT', 5),
    'timeout' => (int) env('CUSTOSPARK_TIMEOUT', 30),

    'endpoints' => [
        'initialize' => env('CUSTOSPARK_INITIALIZE_PATH', '/v1/payments/initialize'),
        'verify' => env('CUSTOSPARK_VERIFY_PATH', '/v1/payments/verify'),
    ],
];
