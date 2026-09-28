<?php

return [
    'admin' => [
        'name' => env('MOBILE_ARENA_ADMIN_NAME'),
        'email' => env('MOBILE_ARENA_ADMIN_EMAIL'),
        'mobile' => env('MOBILE_ARENA_ADMIN_MOBILE'),
        'password' => env('MOBILE_ARENA_ADMIN_PASSWORD'),
    ],
    'inventory' => [
        'reservation_minutes' => (int) env('MOBILE_ARENA_RESERVATION_MINUTES', 30),
        'verification_minutes' => (int) env('MOBILE_ARENA_VERIFICATION_MINUTES', 1440),
        'low_stock_threshold' => (int) env('MOBILE_ARENA_LOW_STOCK_THRESHOLD', 3),
    ],
    'delivery' => [
        'origin' => ['region' => 'MIMAROPA', 'province' => 'Oriental Mindoro', 'city' => 'Calapan City'],
        'fees' => [
            'local' => (int) env('MOBILE_ARENA_DELIVERY_LOCAL_FEE', 99),
            'province' => (int) env('MOBILE_ARENA_DELIVERY_PROVINCE_FEE', 199),
            'regional' => (int) env('MOBILE_ARENA_DELIVERY_REGIONAL_FEE', 349),
            'national' => (int) env('MOBILE_ARENA_DELIVERY_NATIONAL_FEE', 499),
        ],
    ],
    'refund' => [
        'window_days' => (int) env('MOBILE_ARENA_REFUND_WINDOW_DAYS', 7),
    ],
    'notifications' => [
        'email_enabled' => (bool) env('MOBILE_ARENA_EMAIL_NOTIFICATIONS', false),
    ],
];
