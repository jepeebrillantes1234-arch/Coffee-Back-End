<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Payment;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Services\PaymentGateway;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymongoPaymentIntentQrphTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'paymongo.mode' => 'paymongo',
            'paymongo.secret' => 'sk_test_mock_only',
            'paymongo.public_key' => 'pk_test_mock_only',
            'paymongo.webhook_secret' => 'unit-test-webhook-secret',
            'logging.default' => 'null',
        ]);
    }

    public function test_order_payment_intent_returns_qr_and_persists_only_provider_ids_and_expiry(): void
    {
        $customer = $this->user('customer');
        $order = $this->order($customer, 'KJM-PI-ORDER');
        $this->fakeQrCreation();

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/pay');

        $response->assertOk()
            ->assertJsonPath('data.qr_image_url', 'data:image/png;base64,bW9ja19xcl9pbWFnZQ==')
            ->assertJsonPath('data.payment_reference', $order->order_number)
            ->assertJsonPath('data.status', 'awaiting_payment')
            ->assertJsonPath('data.payment_attempt_status', 'pending')
            ->assertJsonMissingPath('data.provider_intent_id')
            ->assertJsonMissingPath('data.client_key');
        $this->assertStringNotContainsString('sk_test_mock_only', $response->getContent());
        $this->assertSame(12500, data_get(Http::recorded()[0][0]->data(), 'data.attributes.amount'));
        $this->assertSame('PHP', data_get(Http::recorded()[0][0]->data(), 'data.attributes.currency'));
        $this->assertSame(['qrph'], data_get(Http::recorded()[0][0]->data(), 'data.attributes.payment_method_allowed'));
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider_intent_id' => 'pi_test_order',
            'provider_session_id' => null,
            'status' => 'pending',
        ]);
        $this->assertNotNull(Payment::where('order_id', $order->id)->first()->qr_expires_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/payment_methods')
            && $request->hasHeader('Authorization')
            && data_get($request->data(), 'data.attributes.type') === 'qrph');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/payment_intents/pi_test_order/attach')
            && data_get($request->data(), 'data.attributes.client_key') === 'pi_test_order_client_key');
    }

    public function test_qr_checkout_retry_reuses_the_existing_order_and_returns_the_qr_details(): void
    {
        config(['shop.payment' => 'online']);
        Settings::set('payment_qrph_enabled', '1');
        $customer = $this->user('customer');
        $category = Category::create(['name' => 'QR Retry']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'QR Retry Coffee',
            'base_price' => 100,
            'is_available' => true,
        ]);
        $payload = [
            'order_type' => 'pickup',
            'payment_method' => 'qrph',
            'checkout_request_id' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];
        $paymentMethodCalls = 0;
        Http::fake(function ($request) use (&$paymentMethodCalls) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/payment_intents')) {
                return Http::response(['data' => ['id' => 'pi_retry_existing', 'attributes' => [
                    'client_key' => 'pi_retry_existing_client_key',
                    'status' => 'awaiting_payment_method',
                ]]], 201);
            }
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/payment_intents/pi_retry_existing')) {
                return Http::response(['data' => ['id' => 'pi_retry_existing', 'attributes' => [
                    'client_key' => 'pi_retry_existing_client_key',
                    'status' => 'awaiting_payment_method',
                ]]], 200);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/payment_methods')) {
                $paymentMethodCalls++;
                if ($paymentMethodCalls === 1) {
                    return Http::response(['errors' => [['code' => 'temporary_failure']]], 500);
                }
                return Http::response(['data' => ['id' => 'pm_retry_existing']], 201);
            }
            if (str_ends_with($request->url(), '/pi_retry_existing/attach')) {
                return Http::response(['data' => ['attributes' => [
                    'status' => 'awaiting_next_action',
                    'next_action' => ['code' => ['image_url' => 'data:image/png;base64,cXJfcmV0cnk=']],
                ]]], 200);
            }
            return Http::response(['errors' => [['code' => 'unexpected_mock_request']]], 500);
        });

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', $payload)->assertStatus(502);
        $this->assertDatabaseCount('orders', 1);
        $order = Order::firstOrFail();
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider_intent_id' => 'pi_retry_existing',
            'status' => 'pending',
        ]);

        $retry = $this->postJson('/api/v1/orders', $payload)->assertCreated();
        $retry->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.status', 'awaiting_payment')
            ->assertJsonPath('data.checkout.qr_image_url', 'data:image/png;base64,cXJfcmV0cnk=');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('pi_retry_existing', Payment::where('order_id', $order->id)->value('provider_intent_id'));
    }

    public function test_another_customer_cannot_create_a_qr_for_someone_elses_order(): void
    {
        $owner = $this->user('customer');
        $other = $this->user('customer');
        $order = $this->order($owner, 'KJM-PI-OWNER-ONLY');
        Http::fake();

        $this->actingAs($other, 'sanctum')->postJson('/api/v1/orders/' . $order->id . '/pay')->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_paid_or_cancelled_orders_cannot_start_a_new_payment_intent(): void
    {
        $customer = $this->user('customer');
        $cancelled = $this->order($customer, 'KJM-PI-CANCELLED');
        $cancelled->update(['status' => 'cancelled']);
        $paid = $this->order($customer, 'KJM-PI-PAID');
        $paid->update(['status' => 'pending', 'payment_status' => 'paid']);
        Http::fake();

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders/' . $cancelled->id . '/pay')->assertStatus(409);
        $this->postJson('/api/v1/orders/' . $paid->id . '/pay')->assertOk()->assertJsonPath('data.payment_status', 'paid');
        Http::assertNothingSent();
    }

    public function test_expired_qr_can_be_replaced_on_the_same_payment_intent(): void
    {
        $customer = $this->user('customer');
        $order = $this->order($customer, 'KJM-PI-RETRY');
        $this->fakeQrCreation();
        $gateway = app(PaymentGateway::class);

        $first = $gateway->createCheckout($order);
        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $intentId = $payment->provider_intent_id;
        $payment->update(['qr_expires_at' => now()->subSecond()]);
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/orders/' . $order->id)
            ->assertOk()
            ->assertJsonPath('data.payment_attempt_status', 'expired');

        $second = $gateway->createCheckout($order);

        $this->assertSame($intentId, $payment->fresh()->provider_intent_id);
        $this->assertTrue($payment->fresh()->qr_expires_at->isFuture());
        $this->assertSame('data:image/png;base64,bW9ja19xcl9pbWFnZQ==', $second['qr_image_url']);
        Http::assertSentCount(6);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/payment_intents/pi_test_order'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/pi_test_order/attach'));
    }

    public function test_failed_payment_webhook_marks_attempt_failed_without_marking_order_paid(): void
    {
        $customer = $this->user('customer');
        $order = $this->order($customer, 'KJM-PI-FAILED');
        $payment = Payment::create([
            'order_id' => $order->id,
            'method' => 'qrph',
            'provider' => 'paymongo',
            'provider_intent_id' => 'pi_test_failed',
            'amount' => 125,
            'status' => 'pending',
        ]);

        $this->postWebhook($this->paymentWebhook('payment.failed', 'pi_test_failed', 'pay_test_failed', 12500))->assertOk();

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/orders/' . $order->id)
            ->assertOk()
            ->assertJsonPath('data.payment_attempt_status', 'failed');
    }

    public function test_membership_checkout_returns_qr_without_exposing_provider_intent_or_secret(): void
    {
        $customer = $this->user('customer');
        $this->fakeQrCreation('pi_test_membership');

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/membership/checkout');

        $response->assertCreated()
            ->assertJsonPath('data.qr_image_url', 'data:image/png;base64,bW9ja19xcl9pbWFnZQ==')
            ->assertJsonPath('data.payment_reference', fn ($value) => is_string($value) && str_starts_with($value, 'KJMM-'))
            ->assertJsonPath('data.amount', 100)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.payment.provider_intent_id')
            ->assertJsonMissingPath('data.payment.client_key');
        $this->assertDatabaseHas('membership_payments', [
            'provider_intent_id' => 'pi_test_membership',
            'status' => 'pending',
        ]);
        $this->assertStringNotContainsString('sk_test_mock_only', $response->getContent());
    }

    public function test_payment_intent_webhook_is_signature_checked_amount_checked_and_idempotent(): void
    {
        $customer = $this->user('customer');
        $order = $this->order($customer, 'KJM-PI-WEBHOOK');
        Payment::create([
            'order_id' => $order->id,
            'method' => 'qrph',
            'provider' => 'paymongo',
            'provider_intent_id' => 'pi_test_paid',
            'amount' => 125,
            'status' => 'pending',
        ]);
        $body = $this->paymentWebhook('payment.paid', 'pi_test_paid', 'pay_test_paid', 12500);

        $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't=1234567890,li=invalid',
        ], $body)->assertUnauthorized();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/orders/' . $order->id)
            ->assertOk()
            ->assertJsonPath('data.payment_attempt_status', 'pending');

        $this->postWebhook($body)->assertOk();
        $paidAt = $order->payments()->first()->fresh()->paid_at;
        $this->postWebhook($body)->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('pay_test_paid', $order->payments()->first()->provider_payment_id);
        $this->assertEquals($paidAt, $order->payments()->first()->paid_at);
    }

    public function test_late_paid_webhook_does_not_reactivate_a_cancelled_order(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => 're_test_late_order']], 200)]);

        $customer = $this->user('customer');
        $order = $this->order($customer, 'KJM-PI-LATE-CANCELLED');
        $order->update([
            'status' => 'cancelled',
            'reject_reason' => 'Customer cancellation',
        ]);
        Payment::create([
            'order_id' => $order->id,
            'method' => 'qrph',
            'provider' => 'paymongo',
            'provider_intent_id' => 'pi_test_late_cancelled',
            'amount' => 125,
            'status' => 'pending',
        ]);

        $body = $this->paymentWebhook(
            'payment.paid',
            'pi_test_late_cancelled',
            'pay_test_late_cancelled',
            12500,
        );
        $this->postWebhook($body)->assertOk();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('refunded', $order->payments()->first()->status);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/refunds')
            && data_get($request->data(), 'data.attributes.payment_id') === 'pay_test_late_cancelled');
    }

    public function test_wrong_amount_does_not_pay_and_membership_payment_intent_webhook_activates_exact_membership(): void
    {
        $customer = $this->user('customer');
        $order = $this->order($customer, 'KJM-PI-WRONG-AMOUNT');
        Payment::create([
            'order_id' => $order->id,
            'method' => 'qrph',
            'provider' => 'paymongo',
            'provider_intent_id' => 'pi_test_wrong',
            'amount' => 125,
            'status' => 'pending',
        ]);
        $this->postWebhook($this->paymentWebhook('payment.paid', 'pi_test_wrong', 'pay_test_wrong', 12499))->assertOk();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/orders/' . $order->id)
            ->assertOk()
            ->assertJsonPath('data.payment_attempt_status', 'pending');

        $membership = Membership::create([
            'user_id' => $customer->id,
            'member_id' => 'KJM-' . Str::upper(Str::random(10)),
            'status' => 'pending',
        ]);
        $membershipPayment = $membership->payments()->create([
            'provider' => 'paymongo',
            'provider_intent_id' => 'pi_test_membership_paid',
            'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'pending',
        ]);
        $this->postWebhook($this->paymentWebhook('payment.paid', 'pi_test_membership_paid', 'pay_test_membership', 10000))->assertOk();
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame('paid', $membershipPayment->fresh()->status);
        $this->assertSame('pay_test_membership', $membershipPayment->fresh()->provider_payment_id);
    }

    public function test_existing_checkout_session_is_returned_without_creating_a_payment_intent(): void
    {
        $customer = $this->user('customer');
        $order = $this->order($customer, 'KJM-LEGACY-CHECKOUT');
        Payment::create([
            'order_id' => $order->id,
            'method' => 'qrph',
            'provider' => 'paymongo',
            'provider_session_id' => 'cs_legacy_pending',
            'checkout_url' => 'https://checkout.example/legacy',
            'amount' => 125,
            'status' => 'pending',
        ]);
        Http::fake();

        $result = app(PaymentGateway::class)->createCheckout($order);

        $this->assertTrue($result['legacy_checkout']);
        $this->assertSame('https://checkout.example/legacy', $result['url']);
        Http::assertNothingSent();
    }

    private function fakeQrCreation(string $intentId = 'pi_test_order'): void
    {
        Http::fake(function ($request) use ($intentId) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/payment_intents/')) {
                return Http::response(['data' => ['id' => $intentId, 'attributes' => [
                    'client_key' => $intentId . '_client_key',
                    'status' => 'awaiting_next_action',
                    'next_action' => ['code' => ['image_url' => 'data:image/png;base64,b2xkX3Fy']],
                ]]], 200);
            }
            if (str_ends_with($request->url(), '/payment_intents')) {
                return Http::response(['data' => ['id' => $intentId, 'attributes' => ['client_key' => $intentId . '_client_key', 'status' => 'awaiting_payment_method']]], 201);
            }
            if (str_ends_with($request->url(), '/payment_methods')) {
                return Http::response(['data' => ['id' => 'pm_test_qrph']], 201);
            }
            if (str_ends_with($request->url(), '/' . $intentId . '/attach')) {
                return Http::response(['data' => ['attributes' => [
                    'status' => 'awaiting_next_action',
                    'next_action' => ['code' => ['image_url' => 'data:image/png;base64,bW9ja19xcl9pbWFnZQ==']],
                ]]], 200);
            }
            return Http::response(['errors' => [['code' => 'unexpected_mock_request']]], 500);
        });
    }

    private function paymentWebhook(string $type, string $intentId, string $paymentId, int $amount): string
    {
        return json_encode(['data' => ['attributes' => [
            'type' => $type,
            'data' => ['id' => $paymentId, 'type' => 'payment', 'attributes' => [
                'payment_intent_id' => $intentId,
                'amount' => $amount,
                'currency' => 'PHP',
                'status' => $type === 'payment.paid' ? 'paid' : 'failed',
            ]],
        ]]], JSON_THROW_ON_ERROR);
    }

    private function postWebhook(string $body)
    {
        $timestamp = '1234567890';
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'unit-test-webhook-secret');
        return $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't=' . $timestamp . ',li=' . $signature,
        ], $body);
    }

    private function order(User $customer, string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'user_id' => $customer->id,
            'order_type' => 'pickup',
            'status' => 'awaiting_payment',
            'subtotal' => 125,
            'total' => 125,
            'payment_method' => 'qrph',
            'payment_status' => 'unpaid',
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' test',
            'username' => $role . Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(12)) . '@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
