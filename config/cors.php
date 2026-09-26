<?php

return [
    'paths' => ['*'],
    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL', 'https://www.futurehouse.ps'),
        'https://futurehouse.ps',
        'https://www.futurehouse.ps',
        app()->environment('local') ? 'http://localhost:3000' : null,
    ])),

    'allowed_origins_patterns' => [
        // حدّد اسم مشروعك بالضبط بدل نمط عام يقبل أي نشر بهاد الاسم
    '#^https://frontend-course-.*-suliman-al-helous-projects\.vercel\.app$#',
    ],

    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];