<?php

return [
    'mode' => env('PAYMENT_MODE', 'paymongo'),
    'secret' => env('PAYMONGO_SECRET_KEY'),
    'public_key' => env('PAYMONGO_PUBLIC_KEY'),
    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
    'base_url' => 'https://api.paymongo.com',
    'public_url' => env('PUBLIC_URL', env('APP_URL')),
    'timeout_minutes' => (int) env('PAYMENT_TIMEOUT_MINUTES', 15),
];
