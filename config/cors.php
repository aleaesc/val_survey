
<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    // Allow requests from the Herd site and local Vite dev server (both http/https)
    'allowed_origins' => [
        'http://valenzuela-survey.test',
        'https://valenzuela-survey.test',
        'http://localhost:5173',
        'https://localhost:5173',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];