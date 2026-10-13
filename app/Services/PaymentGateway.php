<?php
namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PromoUsage;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** PayMongo (QRPh) - Hosted Checkout */
class PaymentGateway
{
    private function http(string $version = 'v1')
    {
        $key = config('paymongo.secret');
        if (!$key) throw new \RuntimeException('Walang PAYMONGO_SECRET_KEY sa .env.');
        return Http::withBasicAuth($key, '')->acceptJson()->asJson()->timeout(20)->baseUrl(rtrim(config('paymongo.base_url'), '/') . '/' . $version);
    }

    private function fail($res, string $what): never
    {
        $detail = $res->json('errors.0.detail') ?? $res->body();
        Log::warning("PayMongo {$what} failed", ['status' => $res->status(), 'body' => $res->json()]);
        throw new \RuntimeException("PayMongo: {$detail}");
    }

    /** Gumagawa ng QRPh checkout page para sa order. */
    public function createCheckout(Order $order): array
    {
        if (config('paymongo.mode') === 'simulated') {
            return $this->createSimulated($order);
        }

        $order->loadMissing('user');
        $base = rtrim((string) config('paymongo.public_url'), '/');

        $res = $this->http('v2')->post('/checkout_sessions', ['data' => ['attributes' => [

            'line_items' => [[
                'amount'   => (int) round($order->total * 100),   // centavos
                'currency' => 'PHP',
                'name'     => "Coffee Shop {$order->order_number}",
                'quantity' => 1,
            ]],
            'payment_method_types' => ['qrph'],
            'reference_number'     => $order->order_number,
            'send_email_receipt'   => false,

            'success_url'          => $base . '/pay/return',
            'cancel_url'           => $base . '/pay/cancel',
            'metadata'             => ['order_id' => (string) $order->id],
        ]]]);

        if (!$res->successful()) $this->fail($res, 'create checkout');

        $sessionId = $res->json('data.id');
        $url = $res->json('data.attributes.checkout_url');

        Payment::updateOrCreate(['order_id' => $order->id], [
            'method' => 'qrph', 'provider' => 'paymongo', 'provider_session_id' => $sessionId,
            'checkout_url' => $url, 'amount' => $order->total, 'status' => 'pending',
            'provider_payment_id' => null, 'paid_at' => null,
        ]);

        return ['url' => $url, 'session_id' => $sessionId];
    }

    /** Tinatanong ang PayMongo kung bayad na ang session (backup kung hindi dumating ang webhook). */
    public function sync(Order $order): bool
    {
        $p = Payment::where('order_id', $order->id)->first();
        if (!$p || !$p->provider_session_id) return false;
        if ($p->provider === 'simulated') return false;   // sa simulate page nagiging paid

        $res = $this->http('v1')->get('/checkout_sessions/' . $p->provider_session_id);
        if (!$res->successful()) {
            Log::warning('PayMongo retrieve failed', ['status' => $res->status(), 'body' => $res->json()]);
            return false;
        }

        foreach ((array) $res->json('data.attributes.payments', []) as $pay) {
            if (data_get($pay, 'attributes.status') === 'paid') {
                $this->markPaid($order, data_get($pay, 'id'), (int) data_get($pay, 'attributes.amount'));
                return true;
            }
        }
        return false;
    }

    /** Idempotent - ligtas kahit ilang beses tawagin (webhook + sync). */
    public function markPaid(Order $order, ?string $paymentId, ?int $amountCentavos = null): void
    {
        $late = false;
        $moved = false;

        DB::transaction(function () use ($order, $paymentId, $amountCentavos, &$late, &$moved) {
            $o = Order::whereKey($order->id)->lockForUpdate()->first();
            if (!$o || in_array($o->payment_status, ['paid', 'refunded'], true)) return;

            if ($amountCentavos !== null && abs($amountCentavos - (int) round($o->total * 100)) > 1) {
                Log::error('PayMongo amount mismatch', ['order' => $o->order_number, 'paid' => $amountCentavos, 'expected' => $o->total]);
                return;
            }

            Payment::updateOrCreate(['order_id' => $o->id], [
                'method' => 'qrph', 'provider' => 'paymongo', 'provider_payment_id' => $paymentId,
                'amount' => $o->total, 'status' => 'paid', 'paid_at' => now(),
            ]);

            $o->payment_status = 'paid';
            if ($o->status === 'awaiting_payment') {
                $o->status = 'pending';          // ngayon pa lang lalabas sa Staff
                $moved = true;
                DB::table('order_status_logs')->insert([
                    'order_id' => $o->id, 'status' => 'pending', 'changed_by' => null, 'created_at' => now(),
                ]);
            } elseif (in_array($o->status, ['cancelled', 'rejected'], true)) {
                $late = true;                    // nagbayad matapos mag-expire - ibalik ang pera
            }
            $o->save();
        });

        $fresh = $order->fresh();
        if ($late) {
            try { $this->refundOrder($fresh); } catch (\Throwable $e) { report($e); }
        } elseif ($moved) {
            Notifier::order($fresh, 'pending');
        }
    }

    public function refundOrder(Order $order): void
    {
        $p = Payment::where('order_id', $order->id)->where('status', 'paid')->first();
        if (!$p || !$p->provider_payment_id) {
            throw new \RuntimeException('Walang PayMongo payment ID para sa refund.');
        }

        if ($p->provider === 'simulated') {
            $this->finishRefund($order, $p);
            return;
        }

        $res = $this->http()->post('/refunds', ['data' => ['attributes' => [
            'amount'     => (int) round($p->amount * 100),
            'payment_id' => $p->provider_payment_id,
            'reason'     => 'requested_by_customer',
            'notes'      => "Refund {$order->order_number}",
        ]]]);
        if (!$res->successful()) $this->fail($res, 'refund');

        $this->finishRefund($order, $p);
    }

    private function finishRefund(Order $order, Payment $p): void
    {
        $p->update(['status' => 'refunded']);
        $order->update(['payment_status' => 'refunded']);
        Notifier::send($order->user_id, 'Na-refund ang bayad 💸',
            'Ibabalik ang ₱' . number_format($p->amount, 2) . ' sa QRPh mo para sa ' . $order->order_number . '.',
            'refund', ['order_id' => $order->id]);
    }

    // ---------------- SIMULATED (pagsubok lang) ----------------
    private function createSimulated(Order $order): array
    {
        $session = 'sim_' . Str::random(24);
        $url = rtrim((string) config('paymongo.public_url'), '/') . '/pay/simulate/' . $session;

        Payment::updateOrCreate(['order_id' => $order->id], [
            'method' => 'qrph', 'provider' => 'simulated', 'provider_session_id' => $session,
            'checkout_url' => $url, 'amount' => $order->total, 'status' => 'pending',
            'provider_payment_id' => null, 'paid_at' => null,
        ]);
        return ['url' => $url, 'session_id' => $session];
    }

    public function simulatePaid(Payment $p): void
    {
        $order = Order::findOrFail($p->order_id);
        $this->markPaid($order, 'sim_pay_' . Str::random(12), (int) round($order->total * 100));
    }

    /** I-cancel ang mga order na hindi nabayaran sa takdang oras. */
    public function expireStale(): int
    {
        $cut = now()->subMinutes(config('paymongo.timeout_minutes'));
        $count = 0;

        foreach (Order::where('status', 'awaiting_payment')->where('created_at', '<', $cut)->limit(50)->get() as $o) {
            try {
                if ($this->sync($o)) continue;      // bayad pala, hindi lang dumating ang webhook
            } catch (\Throwable $e) {
                report($e);
            }
            $o->refresh();
            if ($o->status !== 'awaiting_payment') continue;

            $o->update(['status' => 'cancelled', 'reject_reason' => 'Hindi nabayaran sa takdang oras']);
            DB::table('order_status_logs')->insert([
                'order_id' => $o->id, 'status' => 'cancelled', 'changed_by' => null, 'created_at' => now(),
            ]);
            PromoUsage::where('order_id', $o->id)->delete();
            Payment::where('order_id', $o->id)->update(['status' => 'failed']);
            Notifier::order($o, 'expired');
            $count++;
        }
        return $count;
    }

    // ---------------- WEBHOOK ----------------
    public function verifySignature(string $raw, ?string $header): bool
    {
        $secret = config('paymongo.webhook_secret');
        if (!$secret || !$header) return false;

        $parts = [];
        foreach (explode(',', $header) as $kv) {
            [$k, $v] = array_pad(explode('=', $kv, 2), 2, '');
            $parts[trim($k)] = trim($v);
        }
        $calc = hash_hmac('sha256', ($parts['t'] ?? '') . '.' . $raw, $secret);

        foreach (['te', 'li'] as $k) {
            if (!empty($parts[$k]) && hash_equals($calc, $parts[$k])) return true;
        }
        return false;
    }

    public function handleEvent(array $payload): void
    {
        // Sinusuportahan ang dalawang hugis ng payload ng PayMongo
        $type = data_get($payload, 'data.attributes.type') ?? data_get($payload, 'data.type');
        $res  = data_get($payload, 'data.attributes.data') ?? data_get($payload, 'data.data');

        if ($type !== 'checkout_session.payment.paid' || !$res) return;

        $order = Order::where('order_number', data_get($res, 'attributes.reference_number'))->first();
        if (!$order) {
            Log::warning('PayMongo webhook: walang order', ['ref' => data_get($res, 'attributes.reference_number')]);
            return;
        }

        foreach ((array) data_get($res, 'attributes.payments', []) as $pay) {
            if (data_get($pay, 'attributes.status') === 'paid') {
                $this->markPaid($order, data_get($pay, 'id'), (int) data_get($pay, 'attributes.amount'));
                return;
            }
        }
    }
}
