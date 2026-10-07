<?php

return [
    // COD is the default. Set SHOP_PAYMENT=online to expose GCash checkout.
    'payment' => env('SHOP_PAYMENT', 'cod'),
];
