<?php

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$page = function (string $emoji, string $title, string $text) {
    return response(<<<HTML
<!DOCTYPE html><html lang="tl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<style>body{font-family:system-ui,sans-serif;background:#F5EBDD;color:#4B2E2B;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:24px}
.c{background:#fff;border-radius:20px;padding:36px 28px;max-width:380px;text-align:center;box-shadow:0 6px 24px rgba(0,0,0,.08)}
.e{font-size:56px}h1{font-size:22px;margin:12px 0 8px}p{color:#6b5a58;line-height:1.5}</style></head>
<body><div class="c"><div class="e">{$emoji}</div><h1>{$title}</h1><p>{$text}</p></div></body></html>
HTML);
};

// Pahina kung saan ibabalik ng PayMongo ang customer
Route::get('/pay/return', fn () => $page('âœ…', 'Salamat!', 'Natanggap namin ang bayad mo. Bumalik sa Coffee Shop app para makita ang status ng order mo.'));
Route::get('/pay/cancel', fn () => $page('â†©ï¸', 'Hindi natuloy ang bayad', 'Bumalik sa Coffee Shop app. Puwede mong subukan ulit magbayad sa loob ng ilang minuto.'));

// ============ PEKENG GCASH (para sa pagsubok lang) ============
$simAllowed = fn () => config('paymongo.mode') === 'simulated' && !app()->isProduction();

Route::get('/pay/simulate/{session}', function (string $session) use ($simAllowed) {
    abort_unless($simAllowed(), 404);
    $p = Payment::where('provider', 'simulated')->where('provider_session_id', $session)->firstOrFail();
    $o = Order::findOrFail($p->order_id);
    $amount = number_format($o->total, 2);
    $token = csrf_token();
    $status = $o->payment_status === 'paid' ? '<p style="color:#3C8D5A"><b>Bayad na ang order na ito.</b></p>' : '';

    return response(<<<HTML
<!DOCTYPE html><html lang="tl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Simulated GCash</title>
<style>body{font-family:system-ui,sans-serif;background:#e8f1ff;margin:0;padding:24px;display:flex;justify-content:center}
.c{background:#fff;border-radius:20px;padding:28px;max-width:380px;width:100%;box-shadow:0 6px 24px rgba(0,0,0,.1)}
.tag{background:#fff3cd;color:#8a6d00;border-radius:8px;padding:8px 12px;font-size:13px;margin-bottom:16px}
h1{font-size:20px;margin:0 0 4px;color:#0b5fff}.amt{font-size:34px;font-weight:800;margin:14px 0}
button{width:100%;padding:14px;border:0;border-radius:12px;font-size:16px;font-weight:600;margin-top:10px;cursor:pointer}
.pay{background:#0b5fff;color:#fff}.fail{background:#f1f1f1;color:#333}</style></head>
<body><div class="c">
<div class="tag">âš ï¸ SIMULATED na GCash ito. Hindi ito totoong bayad. Para sa pagsubok lang.</div>
<h1>GCash (Test)</h1>
<div>Order {$o->order_number}</div>
<div class="amt">â‚±{$amount}</div>{$status}
<form method="POST" action="/pay/simulate/{$session}/pay"><input type="hidden" name="_token" value="{$token}"><button class="pay">âœ… Bayaran</button></form>
<form method="POST" action="/pay/simulate/{$session}/fail"><input type="hidden" name="_token" value="{$token}"><button class="fail">âœ– Ipa-fail ang bayad</button></form>
</div></body></html>
HTML);
});

Route::post('/pay/simulate/{session}/{action}', function (Request $request, string $session, string $action) use ($simAllowed) {
    abort_unless($simAllowed(), 404);
    $p = Payment::where('provider', 'simulated')->where('provider_session_id', $session)->firstOrFail();

    if ($action === 'pay') {
        app(PaymentGateway::class)->simulatePaid($p);
        return redirect('/pay/return');
    }
    return redirect('/pay/cancel');
})->where('action', 'pay|fail');

// Simulated membership payment is available only in non-production test mode.
Route::get('/pay/simulate-membership/{session}', function (string $session) use ($simAllowed) {
    abort_unless($simAllowed(), 404);
    $payment = \App\Models\MembershipPayment::where('provider', 'simulated')->where('provider_session_id', $session)->firstOrFail();
    $membership = $payment->membership()->with('user')->firstOrFail();
    $amount = number_format((float) $payment->amount, 2);
    $token = csrf_token();
    $status = $payment->status === 'paid' ? '<p style="color:#198754"><b>Membership payment confirmed.</b></p>' : '';
    return response("<!doctype html><html><head><meta name=viewport content='width=device-width,initial-scale=1'><title>Membership payment</title><style>body{font-family:system-ui;background:#f5ebdd;padding:24px}.card{max-width:420px;margin:auto;background:white;border-radius:18px;padding:28px}button{width:100%;padding:14px;margin-top:12px;border:0;border-radius:10px}</style></head><body><div class=card><h1>KJM Membership</h1><p>Customer: ".e($membership->user->name)."</p><h2>₱{$amount}</h2>{$status}<form method=POST action='/pay/simulate-membership/".e($session)."/pay'><input type=hidden name=_token value='".e($token)."'><button>Confirm simulated payment</button></form><form method=POST action='/pay/simulate-membership/".e($session)."/fail'><input type=hidden name=_token value='".e($token)."'><button>Simulate failed payment</button></form></div></body></html>");
});
Route::post('/pay/simulate-membership/{session}/{action}', function (Request $request, string $session, string $action) use ($simAllowed) {
    abort_unless($simAllowed(), 404);
    $payment = \App\Models\MembershipPayment::where('provider', 'simulated')->where('provider_session_id', $session)->firstOrFail();
    if ($action === 'pay') app(\App\Services\LoyaltyService::class)->markPaid($payment, 'sim_mem_pay_' . \Illuminate\Support\Str::random(12), 10000);
    else $payment->update(['status' => 'failed']);
    return redirect($action === 'pay' ? '/pay/membership/return' : '/pay/membership/cancel');
})->where('action', 'pay|fail');
Route::get('/pay/membership/return', fn () => $page('✅', 'Membership payment received', 'Return to KJM Coffee Shop to refresh your membership card.'));
Route::get('/pay/membership/cancel', fn () => $page('↩️', 'Payment not completed', 'Your membership is still pending. You can try again in the app.'));