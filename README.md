# Staff Panel - Order Queue, Walk-in POS, Daily Transactions

## 1. Kopyahin (PALITAN ang existing kung meron)
  backend/app/Services/CheckoutService.php            -> PALITAN (may walk-in na)
  backend/app/Services/OrderWorkflow.php              -> PALITAN (complete ng walk-in)
  backend/app/Http/Controllers/Staff/*                -> app/Http/Controllers/Staff/  (bagong folder)
  backend/routes/staff.php                            -> routes/staff.php  (bago)
  backend/resources/views/layouts/staff.blade.php     -> resources/views/layouts/
  backend/resources/views/staff/**                    -> resources/views/staff/   (bagong folder)

## 2. Idagdag ang ISANG linya sa DULO ng routes/web.php
  require __DIR__.'/staff.php';

## 3. I-run
  composer dump-autoload
  php artisan optimize:clear

## 4. Gumawa/gamitin ang staff account
  Admin > Staff > + Magdagdag   (o ang demo: staff@coffee.test / password)
  Login: http://127.0.0.1:8000/login  -> mapupunta sa /staff/orders

## 5. Subukan
  - Walk-in POS: /staff/pos  -> pumili ng kape > Bayaran > lalabas ang "I-print ang resibo"
    Pagkatapos ay lalabas ang order sa Order Queue (Inihahanda). I-click ang "Handa na" > "Naibigay na".
  - Online order: umorder sa Customer App > tutunog ang Order Queue (i-click muna ang "I-enable ang tunog")
  - Transactions: /staff/transactions
