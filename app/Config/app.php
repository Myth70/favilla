<?php

return [
    'name'        => env('APP_NAME', 'Favilla'),
    'env'         => env('APP_ENV', 'production'),
    'debug'       => env('APP_DEBUG', false),
    'url'         => env('APP_URL', 'http://localhost'),
    'key'         => env('APP_KEY', ''),
    'base_path'   => env('APP_BASE_PATH', '/public'),

    'maintenance' => (bool) env('MAINTENANCE_MODE', false)
                  || file_exists((defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/maintenance.enabled'),

    'session' => [
        'driver'   => env('SESSION_DRIVER', 'file'),
        'lifetime' => (int) env('SESSION_LIFETIME', 480),
        'path'     => env('SESSION_PATH', '/storage/sessions'),
    ],

    'rate_limit' => [
        'login_max'    => (int) env('RATE_LIMIT_LOGIN', 5),
        'login_window' => (int) env('RATE_LIMIT_WINDOW', 15),
    ],

    'log' => [
        'level' => env('LOG_LEVEL', 'debug'),
        'path'  => env('LOG_PATH', '/storage/logs'),
    ],
];
