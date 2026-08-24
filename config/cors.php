<?php

return [
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'https://www.futurehouse.ps'),
        'https://futurehouse.ps',
        'https://www.futurehouse.ps',
        'http://localhost:3000',
        'http://localhost:5173',
    ],

    'allowed_origins_patterns' => [
        '#^https://frontend-course-.*\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];