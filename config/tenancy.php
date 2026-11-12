<?php

return [
    'domain_suffix' => env('TENANT_DOMAIN_SUFFIX', 'custospark.events'),

    'central_domains' => array_filter([
        'localhost',
        '127.0.0.1',
        parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST),
    ]),
];
