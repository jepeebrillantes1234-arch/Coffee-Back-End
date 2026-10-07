<?php

return [
    // .env:  PAYMONGO_SECRET_KEY=sk_test_xxxxx
    // 'paymongo' = totoong GCash via PayMongo | 'simulated' = PEKENG bayad para sa pagsubok (hindi gagana sa production)
    'mode'           => env('PAYMENT_MODE', 'paymongo'),
    'secret'         => env('PAYMONGO_SECRET_KEY'),
    // .env:  PAYMONGO_WEBHOOK_SECRET=whsk_xxxxx
    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
    'base_url'       => 'https://api.paymongo.com/v1',
    // Pampublikong URL ng server mo (ngrok URL o domain). Dito babalik ang customer pagkatapos magbayad.
    'public_url'     => env('PUBLIC_URL', env('APP_URL')),
    // Ilang minuto bago ma-cancel ang order na hindi nabayaran
    'timeout_minutes'=> (int) env('PAYMENT_TIMEOUT_MINUTES', 15),
];
