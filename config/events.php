<?php

return [
    'fees' => [
        'gateway_percent' => (float) env('EVENTS_GATEWAY_FEE_PERCENT', 3.5),
        'custospark_percent' => (float) env('EVENTS_CUSTOSPARK_FEE_PERCENT', 1.5),
        'default_bearer' => env('EVENTS_DEFAULT_FEE_BEARER', 'customer'),
    ],
];
