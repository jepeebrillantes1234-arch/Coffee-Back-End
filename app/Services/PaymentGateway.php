<?php
namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\MembershipPayment;
use App\Models\PromoUsage;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** PayMongo QR Ph Payment Intents plus legacy Checkout Session reconciliation. */
class PaymentGateway
{
    private function http(string $version = 'v1', ?string $key = null)
    {
        $key ??= config('paymongo.secret');
        if (!$key) throw new \RuntimeException('Walang PAYMONGO_SECRET_KEY sa .env.');
        return Http::withBasicAuth($key, '')->acceptJson()->asJson()->timeout(20)->baseUrl(rtrim(config('paymongo.base_url'), '/') . '/' . $version);
    }

    private function fail($res, string $what): never
    {
        $code = $res->json('errors.0.code');
        Log::warning("PayMongo {$what} failed", ['status' => $res->status(), 'provider_error_code' => is_string($code) ? $code : null]);
        throw new \RuntimeException("PayMongo {$what} request failed with HTTP {$res->status()}.");
    }

    /** Creates a dynamic QR Ph Payment Intent for an order. */
    public function createCheckout(Order $order): array
    {
        if ($order->status !== 'awaiting_payment' || $order->payment_status !== 'unpaid') {
            throw new \RuntimeException('This order is not awaiting an online payment.');
        }
        if (config('paymongo.mode') === 'simulated') {
            return $this->createSimulated($order);
        }

        $payment = Payment::firstOrNew(['order_id' => $order->id]);

        // Keep already-created Checkout Sessions payable while the app is upgraded.
        if ($payment->provider_session_id && !$payment->provider_intent_id) {
            return [
                'url' => $payment->checkout_url,
                'session_id' => $payment->provider_session_id,
                'legacy_checkout' => true,
                'payment_reference' => $order->order_number,
                'amount' => (float) $payment->amount,
                'expires_at' => null,
                'status' => $payment->status,
            ];
        }

        $payment->fill([
            'method' => 'qrph', 'provider' => 'paymongo',
            'amount' => $order->total, 'status' => 'pending',
        ])->save();

        return $this->createIntentQr($payment, (float) $order->total, $order->order_number, "Order {$order->order_number}");
    }

    /** Creates or refreshes the membership QR while reusing its payment record. */
    public function createMembershipQr(MembershipPayment $payment): array
    {
        return $this->createIntentQr(
            $payment,
            (float) $payment->amount,
            $payment->payment_reference,
            'KJM Coffee Shop membership ' . $payment->payment_reference,
        );
    }

    /** Creates one intent per local payment record, then attaches a QR Ph method. */
    private function createIntentQr($payment, float $amount, string $reference, string $description): array
    {
        $publicKey = config('paymongo.public_key');
        if (!$publicKey) throw new \RuntimeException('QR Ph payment is not configured.');

        $intentId = $payment->provider_intent_id;
        $intent = null;
        if ($intentId) {
            $existing = $this->http()->get('/payment_intents/' . $intentId);
            if (!$existing->successful()) $this->fail($existing, 'retrieve payment intent');
            $intent = $existing->json('data');
        } else {
            $created = $this->http()->withHeaders(['Idempotency-Key' => $reference])
                ->post('/payment_intents', ['data' => ['attributes' => [
                    'amount' => (int) round($amount * 100),
                    'currency' => 'PHP',
                    'payment_method_allowed' => ['qrph'],
                    'description' => $description,
                    'metadata' => ['payment_reference' => $reference],
                ]]]);
            if (!$created->successful()) $this->fail($created, 'create payment intent');
            $intent = $created->json('data');
            $intentId = data_get($intent, 'id');
            if (!$intentId) throw new \RuntimeException('PayMongo did not return a payment intent ID.');
            $payment->forceFill([
                'provider' => 'paymongo',
                'provider_intent_id' => $intentId,
                'provider_session_id' => null,
                'checkout_url' => null,
                'status' => 'pending',
            ])->save();
        }

        $intentAttributes = (array) data_get($intent, 'attributes', []);
        $providerStatus = (string) ($intentAttributes['status'] ?? '');
        $image = data_get($intentAttributes, 'next_action.code.image_url');
        $expiry = $payment->qr_expires_at;
        if (is_string($image) && $image !== '' && $expiry && $expiry->isFuture()) {
            return $this->qrPayload($payment, $reference, $amount, $image, $providerStatus);
        }

        if (in_array($providerStatus, ['succeeded', 'processing', 'cancelled'], true)) {
            return $this->qrPayload($payment, $reference, $amount, null, $providerStatus);
        }

        $clientKey = $intentAttributes['client_key'] ?? null;
        if (!$clientKey) throw new \RuntimeException('PayMongo did not return the payment intent client key.');

        $expirySeconds = 1800;
        $methodKey = hash('sha256', $reference . '|' . ($payment->qr_expires_at?->timestamp ?? 'initial'));
        $method = $this->http('v1', $publicKey)->withHeaders(['Idempotency-Key' => $methodKey])
            ->post('/payment_methods', ['data' => ['attributes' => [
            'type' => 'qrph',
            'expiry_seconds' => $expirySeconds,
        ]]]);
        if (!$method->successful()) $this->fail($method, 'create QR Ph payment method');
        $methodId = $method->json('data.id');
        if (!$methodId) throw new \RuntimeException('PayMongo did not return a QR Ph payment method ID.');

        $attached = $this->http('v1', $publicKey)->post('/payment_intents/' . $intentId . '/attach', ['data' => ['attributes' => [
            'payment_method' => $methodId,
            'client_key' => $clientKey,
        ]]]);
        if (!$attached->successful()) $this->fail($attached, 'attach QR Ph payment method');

        $image = $attached->json('data.attributes.next_action.code.image_url');
        $providerStatus = (string) $attached->json('data.attributes.status', 'awaiting_next_action');
        if (!is_string($image) || $image === '') throw new \RuntimeException('PayMongo did not return a QR image.');

        $payment->forceFill([
            'status' => 'pending',
            'qr_expires_at' => now()->addSeconds($expirySeconds),
        ])->save();

        return $this->qrPayload($payment, $reference, $amount, $image, $providerStatus);
    }

    private function qrPayload($payment, string $reference, float $amount, ?string $image, string $providerStatus): array
    {
        $localStatus = $payment->status === 'paid' ? 'paid' : (
            $payment->qr_expires_at && $payment->qr_expires_at->isPast() ? 'expired' :
            ($payment->status === 'failed' ? 'failed' : 'pending')
        );
        return [
            'url' => null,
            'qr_image_url' => $image,
            'payment_reference' => $reference,
            'amount' => $amount,
            'expires_at' => $payment->qr_expires_at?->toIso8601String(),
            'status' => $localStatus,
            'provider_status' => $providerStatus,
        ];
    }

    /** Tinatanong ang PayMongo kung bayad na ang session (backup kung hindi dumating ang webhook). */
    public function sync(Order $order): bool
    {
        $p = Payment::where('order_id', $order->id)->first();
        if ($p?->provider_intent_id) {
            // Payment Intent settlement is committed by the signed webhook, not by a client poll.
            $res = $this->http()->get('/payment_intents/' . $p->provider_intent_id);
            if (!$res->successful()) {
                Log::warning('PayMongo payment intent retrieve failed', ['status' => $res->status()]);
                return false;
            }
            return $p->status === 'paid';
        }
        if (!$p || !$p->provider_session_id) return false;
        if ($p->provider === 'simulated') return false;   // sa simulate page nagiging paid

        $res = $this->http('v1')->get('/checkout_sessions/' . $p->provider_session_id);
        if (!$res->successful()) {
            Log::warning('PayMongo retrieve failed', ['status' => $res->status()]);
            return false;
        }

        foreach ((array) $res->json('data.attributes.payments', []) as $pay) {
            if (data_get($pay, 'attributes.status') === 'paid') {
                return $this->markPaid($order, data_get($pay, 'id'), (int) data_get($pay, 'attributes.amount'));
            }
        }
        return false;
    }

    /** Idempotent - ligtas kahit ilang beses tawagin (webhook + sync). */
    public function markPaid(Order $order, ?string $paymentId, ?int $amountCentavos = null): bool
    {
        $late = false;
        $moved = false;
        $accepted = false;

        DB::transaction(function () use ($order, $paymentId, $amountCentavos, &$late, &$moved, &$accepted) {
            $o = Order::whereKey($order->id)->lockForUpdate()->first();
            if (!$o || in_array($o->payment_status, ['paid', 'refunded'], true)) return;

            if ($amountCentavos !== null && $amountCentavos !== (int) round($o->total * 100)) {
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
            $accepted = true;
        });

        $fresh = $order->fresh();
        if ($late) {
            try { $this->refundOrder($fresh); } catch (\Throwable $e) { report($e); }
        } elseif ($accepted) {
            Notifier::paymentConfirmed($fresh);
        }
        return $accepted;
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
        Notifier::send($order->user_id, 'Refund status updated',
            'A refund for your order has been processed. Open Order Details for the latest information.',
            'refund', [
                'order_id' => (string) $order->id,
                'status' => 'refunded',
                'payment_status' => 'refunded',
            ]);
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
            Payment::where('order_id', $o->id)->where('status', 'pending')->update(['status' => 'expired']);
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
        $type = data_get($payload, 'data.attributes.type') ?? data_get($payload, 'data.type');
        $res  = data_get($payload, 'data.attributes.data') ?? data_get($payload, 'data.data');
        if (!$res) return;

        // Preserve existing pending Checkout Sessions during the migration.
        if ($type === 'checkout_session.payment.paid') {
            $reference = (string) data_get($res, 'attributes.reference_number', '');
            $order = Order::where('order_number', $reference)->first();
            if (!$order) {
                foreach ((array) data_get($res, 'attributes.payments', []) as $legacyPayment) {
                    if (data_get($legacyPayment, 'attributes.status') === 'paid') {
                        app(LoyaltyService::class)->markPaidByReference(
                            $reference,
                            data_get($legacyPayment, 'id'),
                            (int) data_get($legacyPayment, 'attributes.amount'),
                        );
                        break;
                    }
                }
                return;
            }
            foreach ((array) data_get($res, 'attributes.payments', []) as $legacyPayment) {
                if (data_get($legacyPayment, 'attributes.status') === 'paid') {
                    $this->markPaid($order, data_get($legacyPayment, 'id'), (int) data_get($legacyPayment, 'attributes.amount'));
                    return;
                }
            }
            return;
        }

        $intentId = $type === 'payment.paid' || $type === 'payment.failed'
            ? data_get($res, 'attributes.payment_intent_id')
            : ($type === 'payment_intent.succeeded' || $type === 'payment_intent.awaiting_payment_method'
                ? data_get($res, 'id')
                : data_get($res, 'attributes.payment_intent_id'));

        if (!$intentId) return;

        if ($type === 'payment.paid' && data_get($res, 'attributes.status') === 'paid') {
            $paymentId = data_get($res, 'id');
            $amount = (int) data_get($res, 'attributes.amount', 0);
            $currency = data_get($res, 'attributes.currency');

            $payment = Payment::where('provider_intent_id', $intentId)->first();
            if ($payment) {
                if ($currency !== 'PHP') {
                    Log::warning('PayMongo order currency mismatch', ['payment_id' => $payment->id]);
                    return;
                }
                $this->markPaid($payment->order, $paymentId, $amount);
                return;
            }

            $membershipPayment = MembershipPayment::where('provider_intent_id', $intentId)->first();
            if ($membershipPayment && $currency === 'PHP') {
                app(LoyaltyService::class)->markPaid(
                    $membershipPayment,
                    $paymentId,
                    $amount,
                );
            }
            return;
        }

        if ($type === 'payment.failed') {
            Payment::where('provider_intent_id', $intentId)->where('status', '!=', 'paid')->update(['status' => 'failed']);
            MembershipPayment::where('provider_intent_id', $intentId)->where('status', 'pending')->update(['status' => 'failed']);
            return;
        }

        if (in_array($type, ['qrph.expired', 'payment_intent.awaiting_payment_method'], true)) {
            Payment::where('provider_intent_id', $intentId)->where('status', '!=', 'paid')->update(['qr_expires_at' => now()]);
            MembershipPayment::where('provider_intent_id', $intentId)->where('status', 'pending')->update(['qr_expires_at' => now()]);
        }
    }
}
