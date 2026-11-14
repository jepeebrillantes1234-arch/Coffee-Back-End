<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundAttempt;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefundManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['paymongo.mode' => 'paymongo', 'paymongo.secret' => 'sk_test_mock_only', 'paymongo.webhook_secret' => 'refund-test-secret', 'logging.default' => 'null']);
    }

    public function test_customer_can_request_refund_only_for_verified_paid_qrph_and_duplicate_submission_is_idempotent(): void
    {
        $customer = $this->user('customer');
        $this->user('admin');
        $order = $this->orderWithPayment($customer);
        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/orders/{$order->id}/refund-requests", $this->requestBody())->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->postJson("/api/v1/orders/{$order->id}/refund-requests", $this->requestBody())->assertOk()->assertJsonPath('already_exists', true);
        $this->assertDatabaseCount('refund_requests', 1);
        $this->assertDatabaseCount('app_notifications', 2); // customer plus admin, created once.

        $unpaid = $this->orderWithPayment($customer, 'unpaid');
        $this->postJson("/api/v1/orders/{$unpaid->id}/refund-requests", $this->requestBody())->assertStatus(422);
        $cod = $this->orderWithPayment($customer, 'paid', 'cod');
        $this->postJson("/api/v1/orders/{$cod->id}/refund-requests", $this->requestBody())->assertStatus(422);

        $other = $this->user('customer');
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/orders/'.$order->id.'/refund-requests')->assertNotFound();
    }

    public function test_admin_decision_requires_reason_and_records_approval_or_rejection(): void
    {
        $customer = $this->user('customer');
        $admin = $this->user('admin');
        $order = $this->orderWithPayment($customer);
        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/orders/{$order->id}/refund-requests", $this->requestBody())->assertCreated();
        $refund = RefundRequest::firstOrFail();

        $this->actingAs($admin)->post(route('admin.refunds.approve', $refund), ['approved_amount_cents' => 5000])->assertSessionHasErrors('reason');
        $this->post(route('admin.refunds.approve', $refund), ['approved_amount_cents' => 5000, 'reason' => 'Approved partial refund'])->assertRedirect();
        $refund->refresh();
        $this->assertSame('approved', $refund->status);
        $this->assertSame(5000, $refund->approved_amount_cents);
        $this->assertSame(0, $refund->refunded_amount_cents);
        $this->get(route('admin.refunds.show', $refund))
            ->assertOk()
            ->assertSee('Approved')
            ->assertSee('Refund confirmed')
            ->assertDontSee('Succeeded');
        $this->assertSame($admin->id, $refund->reviewed_by);
        $this->assertNotNull($refund->reviewed_at);
        $this->assertDatabaseHas('activity_logs', ['module' => 'refunds', 'action' => 'approve']);

        $secondOrder = $this->orderWithPayment($customer, 'paid', 'qrph', 'KJM-REFUND-REJECT');
        $this->actingAs($customer, 'sanctum');
        $this->postJson("/api/v1/orders/{$secondOrder->id}/refund-requests", $this->requestBody())->assertCreated();
        $second = RefundRequest::where('order_id', $secondOrder->id)->firstOrFail();
        $this->actingAs($admin);
        $this->post(route('admin.refunds.reject', $second), ['reason' => 'Evidence does not support the request'])->assertRedirect();
        $this->assertSame('rejected', $second->fresh()->status);
        $this->assertSame('Evidence does not support the request', $second->fresh()->decision_reason);
    }

    public function test_reconcile_without_provider_attempt_shows_process_instruction_without_submitting_refund(): void
    {
        $customer = $this->user('customer');
        $admin = $this->user('admin');
        $order = $this->orderWithPayment($customer);
        $refund = RefundRequest::create([
            'order_id' => $order->id, 'payment_id' => $order->payments()->first()->id, 'user_id' => $customer->id,
            'reason' => 'order_issue', 'explanation' => 'The paid order has an issue.', 'status' => 'approved',
            'source' => 'customer', 'approved_amount_cents' => 5000, 'refunded_amount_cents' => 0,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.refunds.reconcile', $refund))
            ->assertRedirect(route('admin.refunds.show', $refund))
            ->assertSessionHas('success', 'No PayMongo refund attempt exists yet. Use Process / Retry safely to submit the approved refund.');

        $this->assertDatabaseCount('refund_attempts', 0);
        $this->assertSame('paid', $order->payments()->first()->fresh()->status);
        $this->assertSame(0, $refund->fresh()->refunded_amount_cents);
    }
    public function test_admin_can_approve_a_one_peso_refund_using_peso_amount_input(): void
    {
        $customer = $this->user('customer');
        $admin = $this->user('admin');
        $order = $this->orderWithPayment($customer);
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/v1/orders/{$order->id}/refund-requests", $this->requestBody())
            ->assertCreated();
        $refund = RefundRequest::firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.refunds.approve', $refund), [
                'approved_amount_php' => '1.00',
                'reason' => 'Approve minimum refund',
            ])
            ->assertRedirect();

        $this->assertSame('approved', $refund->fresh()->status);
        $this->assertSame(100, $refund->fresh()->approved_amount_cents);
    }

    public function test_admin_refund_history_page_shows_request_details_and_staff_is_denied(): void
    {
        $customer = $this->user('customer');
        $admin = $this->user('admin');
        $staff = $this->user('staff');
        $order = $this->orderWithPayment($customer, 'paid', 'qrph', 'KJM-REFUND-VIEW');
        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/orders/{$order->id}/refund-requests", $this->requestBody())->assertCreated();
        $refund = RefundRequest::firstOrFail();

        $this->actingAs($admin)->get(route('admin.refunds.index'))->assertOk()->assertSee('Refund Management')->assertSee($order->order_number);
        $this->get(route('admin.refunds.show', $refund))->assertOk()->assertSee('PayMongo Payment ID')->assertSee('The paid order has an issue.');
        $this->actingAs($staff)->get(route('admin.refunds.index'))->assertForbidden();
    }

    public function test_partial_refund_only_marks_refunded_amount_after_paymongo_confirms_succeeded(): void
    {
        $customer = $this->user('customer');
        $admin = $this->user('admin');
        $order = $this->orderWithPayment($customer);
        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/orders/{$order->id}/refund-requests", $this->requestBody())->assertCreated();
        $refund = RefundRequest::firstOrFail();
        $this->actingAs($admin)->post(route('admin.refunds.approve', $refund), ['approved_amount_cents' => 5000, 'reason' => 'Approved partial refund'])->assertRedirect();
        Http::fake(['https://api.paymongo.com/refunds' => Http::response(['data' => $this->providerRefund('re_partial', 'processing', 5000, 'pay_original')], 201)]);
        $this->post(route('admin.refunds.process', $refund))->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $order->payments()->first()->fresh()->status);
        $attempt = RefundAttempt::firstOrFail();

        app(\App\Services\RefundService::class)->applyWebhookResource($this->providerRefund('re_partial', 'succeeded', 5000, 'pay_original'));
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
        $this->assertSame('partially_refunded', $order->payments()->first()->fresh()->status);
        $this->assertSame(5000, $refund->fresh()->refunded_amount_cents);
        $this->assertSame('refunded', $refund->fresh()->status);
        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->get(route('admin.refunds.show', $refund))
            ->assertOk()
            ->assertSee('Succeeded')
            ->assertSee('Refund confirmed');
    }

    public function test_ambiguous_refund_retry_reuses_same_idempotency_key_and_success_webhook_is_idempotent(): void
    {
        $customer = $this->user('customer');
        $admin = $this->user('admin');
        $order = $this->orderWithPayment($customer);
        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/orders/{$order->id}/refund-requests", $this->requestBody())->assertCreated();
        $refund = RefundRequest::firstOrFail();
        $this->actingAs($admin)->post(route('admin.refunds.approve', $refund), ['approved_amount_cents' => 12500, 'reason' => 'Full refund approved'])->assertRedirect();
        $calls = 0;
        Http::fake(function ($request) use (&$calls) {
            if (str_ends_with($request->url(), '/refunds') && $request->method() === 'POST') {
                $calls++;
                if ($calls === 1) return Http::response([], 503);
                return Http::response(['data' => $this->providerRefund('re_safe', 'succeeded', 12500, 'pay_original', data_get($request->data(), 'data.attributes.notes'))], 201);
            }
            return Http::response([], 500);
        });

        $this->post(route('admin.refunds.process', $refund))->assertRedirect();
        $this->assertSame('needs_review', $refund->fresh()->status);
        $key = RefundAttempt::firstOrFail()->idempotency_key;
        $this->post(route('admin.refunds.process', $refund))->assertRedirect();
        $this->assertSame(2, $calls);
        $posts = collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() === 'POST' && str_ends_with($pair[0]->url(), '/refunds'));
        $this->assertCount(2, $posts);
        $this->assertSame($key, $posts->first()[0]->header('Idempotency-Key')[0]);
        $this->assertSame($key, $posts->last()[0]->header('Idempotency-Key')[0]);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('refunded', $order->fresh()->payments()->first()->status);
        $this->assertSame('refunded', $refund->fresh()->status);

        $resource = $this->providerRefund('re_safe', 'succeeded', 12500, 'pay_original');
        $this->postWebhook($this->refundWebhook($resource))->assertOk();
        $notifications = \Illuminate\Support\Facades\DB::table('app_notifications')->count();
        $this->postWebhook($this->refundWebhook($resource))->assertOk();
        $this->assertSame($notifications, \Illuminate\Support\Facades\DB::table('app_notifications')->count());
        $this->assertDatabaseCount('refund_attempts', 1);
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_late_payment_refund_failure_is_recoverable_and_cancelled_order_stays_cancelled(): void
    {
        $customer = $this->user('customer');
        $order = $this->orderWithPayment($customer, 'unpaid', 'qrph', 'KJM-REFUND-LATE');
        $order->update(['status' => 'cancelled']);
        $order->payments()->first()->update(['status' => 'pending', 'provider_intent_id' => 'pi_late']);
        $calls = 0;
        Http::fake(function ($request) use (&$calls) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/refunds')) {
                $calls++;
                return Http::response(['data' => $this->providerRefund('re_late_'.$calls, $calls === 1 ? 'failed' : 'succeeded', 12500, 'pay_late', data_get($request->data(), 'data.attributes.notes'))], 201);
            }
            return Http::response([], 500);
        });
        $body = $this->paymentWebhook('pi_late', 'pay_late', 12500);
        $this->postWebhook($body)->assertOk();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('failed', RefundAttempt::firstOrFail()->status);
        $this->postWebhook($body)->assertOk();
        $this->assertSame(2, $calls);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('refunded', $order->payments()->first()->fresh()->status);
        $this->assertSame(2, RefundAttempt::count());
    }

    public function test_ambiguous_attempt_after_idempotency_expiry_is_reconciled_without_a_new_post(): void
    {
        $customer = $this->user('customer');
        $admin = $this->user('admin');
        $order = $this->orderWithPayment($customer);
        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/orders/{$order->id}/refund-requests", $this->requestBody())->assertCreated();
        $refund = RefundRequest::firstOrFail();
        $this->actingAs($admin)->post(route('admin.refunds.approve', $refund), ['approved_amount_cents' => 12500, 'reason' => 'Full refund approved'])->assertRedirect();
        Http::fake(function ($request) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/refunds')) return Http::response([], 503);
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/refunds')) return Http::response(['data' => []], 200);
            return Http::response([], 500);
        });
        $this->post(route('admin.refunds.process', $refund))->assertRedirect();
        $attempt = RefundAttempt::firstOrFail();
        $attempt->update(['idempotency_key_created_at' => now()->subHours(25)]);
        $this->post(route('admin.refunds.process', $refund))->assertRedirect();
        $this->assertSame('ambiguous', $attempt->fresh()->status);
        $this->assertSame('needs_review', $refund->fresh()->status);
        $this->assertDatabaseCount('refund_attempts', 1);
        Http::assertSentCount(2); // one create; one read-only list reconciliation.
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_stale_refund_webhook_cannot_regress_succeeded_status_or_confirmed_totals(): void
    {
        $customer = $this->user('customer');
        $order = $this->orderWithPayment($customer);
        $payment = $order->payments()->first();
        $refund = RefundRequest::create([
            'order_id' => $order->id, 'payment_id' => $payment->id, 'user_id' => $customer->id,
            'reason' => 'order_issue', 'explanation' => 'The paid order has an issue.', 'status' => 'refunded',
            'source' => 'customer', 'requested_amount_cents' => 5000, 'approved_amount_cents' => 5000,
            'refunded_amount_cents' => 5000,
        ]);
        $attempt = RefundAttempt::create([
            'refund_request_id' => $refund->id, 'provider_refund_id' => 're_stale_timestamp',
            'amount_cents' => 5000, 'status' => 'succeeded', 'provider_status' => 'succeeded',
            'provider_updated_at' => now(), 'idempotency_key' => (string) Str::uuid(),
            'idempotency_key_created_at' => now(),
        ]);
        $payment->update(['status' => 'partially_refunded']);
        $order->update(['payment_status' => 'partially_refunded']);
        $stale = $this->providerRefund('re_stale_timestamp', 'processing', 5000, 'pay_original');
        $stale['attributes']['updated_at'] = now()->subMinutes(2)->timestamp;

        $this->postWebhook($this->refundWebhook($stale))->assertOk();

        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertSame(5000, $refund->fresh()->refunded_amount_cents);
        $this->assertSame('partially_refunded', $payment->fresh()->status);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
    }

    public function test_succeeded_refund_cannot_be_downgraded_by_conflicting_webhook_without_timestamp(): void
    {
        $customer = $this->user('customer');
        $order = $this->orderWithPayment($customer);
        $payment = $order->payments()->first();
        $refund = RefundRequest::create([
            'order_id' => $order->id, 'payment_id' => $payment->id, 'user_id' => $customer->id,
            'reason' => 'order_issue', 'explanation' => 'The paid order has an issue.', 'status' => 'refunded',
            'source' => 'customer', 'requested_amount_cents' => 5000, 'approved_amount_cents' => 5000,
            'refunded_amount_cents' => 5000,
        ]);
        $attempt = RefundAttempt::create([
            'refund_request_id' => $refund->id, 'provider_refund_id' => 're_succeeded_terminal',
            'amount_cents' => 5000, 'status' => 'succeeded', 'provider_status' => 'succeeded',
            'idempotency_key' => (string) Str::uuid(), 'idempotency_key_created_at' => now(),
        ]);
        $payment->update(['status' => 'partially_refunded']);
        $order->update(['payment_status' => 'partially_refunded']);

        $this->postWebhook($this->refundWebhook($this->providerRefund('re_succeeded_terminal', 'failed', 5000, 'pay_original')))->assertOk();
        $mismatched = $this->providerRefund('re_succeeded_terminal', 'succeeded', 6000, 'pay_original');
        app(\App\Services\RefundService::class)->applyWebhookResource($mismatched);

        $this->assertSame('succeeded', $attempt->fresh()->status);
        $this->assertSame(5000, $refund->fresh()->refunded_amount_cents);
        $this->assertSame('partially_refunded', $payment->fresh()->status);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
    }

    public function test_admin_approval_rejects_mismatched_payment_order_and_wrong_customer_owner(): void
    {
        $customer = $this->user('customer');
        $admin = $this->user('admin');
        $order = $this->orderWithPayment($customer);
        $payment = $order->payments()->first();
        $refund = RefundRequest::create([
            'order_id' => $order->id, 'payment_id' => $payment->id, 'user_id' => $customer->id,
            'reason' => 'order_issue', 'explanation' => 'The paid order has an issue.', 'status' => 'pending', 'source' => 'customer',
        ]);
        $otherOrder = $this->orderWithPayment($customer, 'paid', 'qrph', 'KJM-OTHER-REFUND');
        $refund->update(['payment_id' => $otherOrder->payments()->first()->id]);

        $this->actingAs($admin)->post(route('admin.refunds.approve', $refund), ['approved_amount_cents' => 5000, 'reason' => 'Partial refund approved'])->assertUnprocessable();
        $this->assertSame('pending', $refund->fresh()->status);

        $refund->update(['payment_id' => $payment->id, 'user_id' => $this->user('customer')->id]);
        $this->post(route('admin.refunds.approve', $refund), ['approved_amount_cents' => 5000, 'reason' => 'Partial refund approved'])->assertUnprocessable();
        $this->assertSame('pending', $refund->fresh()->status);
    }

    public function test_refund_processing_rejects_mismatched_payment_order_and_wrong_customer_owner(): void
    {
        $customer = $this->user('customer');
        $admin = $this->user('admin');
        $order = $this->orderWithPayment($customer);
        $payment = $order->payments()->first();
        $refund = RefundRequest::create([
            'order_id' => $order->id, 'payment_id' => $payment->id, 'user_id' => $customer->id,
            'reason' => 'order_issue', 'explanation' => 'The paid order has an issue.', 'status' => 'approved',
            'source' => 'customer', 'approved_amount_cents' => 5000,
        ]);
        $otherOrder = $this->orderWithPayment($customer, 'paid', 'qrph', 'KJM-OTHER-PROCESS');
        $refund->update(['payment_id' => $otherOrder->payments()->first()->id]);

        $this->actingAs($admin)->post(route('admin.refunds.process', $refund))->assertUnprocessable();
        $this->assertDatabaseCount('refund_attempts', 0);

        $refund->update(['payment_id' => $payment->id, 'user_id' => $this->user('customer')->id]);
        $this->post(route('admin.refunds.process', $refund))->assertUnprocessable();
        $this->assertDatabaseCount('refund_attempts', 0);
    }

    private function orderWithPayment(User $customer, string $paymentStatus = 'paid', string $method = 'qrph', string $number = 'KJM-REFUND-TEST'): Order
    {
        $order = Order::create(['order_number' => $number.'-'.Str::upper(Str::random(4)), 'user_id' => $customer->id, 'order_type' => 'pickup', 'status' => $paymentStatus === 'paid' ? 'completed' : 'awaiting_payment', 'subtotal' => 125, 'total' => 125, 'payment_method' => $method, 'payment_status' => $paymentStatus]);
        Payment::create(['order_id' => $order->id, 'method' => $method, 'provider' => $method === 'qrph' ? 'paymongo' : 'manual', 'provider_payment_id' => $paymentStatus === 'paid' && $method === 'qrph' ? 'pay_original' : null, 'amount' => 125, 'status' => $paymentStatus, 'paid_at' => $paymentStatus === 'paid' ? now() : null]);
        return $order;
    }

    private function requestBody(): array { return ['reason' => 'order_issue', 'explanation' => 'The paid order has an issue.']; }
    private function user(string $role): User { return User::create(['name' => ucfirst($role).' Refund Test', 'username' => $role.Str::lower(Str::random(8)), 'email' => Str::lower(Str::random(10)).'@refund.test', 'password' => 'password', 'role' => $role, 'status' => 'active']); }

    private function providerRefund(string $id, string $status, int $amount, string $paymentId, ?string $notes = null): array
    {
        return ['id' => $id, 'type' => 'refund', 'attributes' => ['payment_id' => $paymentId, 'amount' => $amount, 'currency' => 'PHP', 'status' => $status, 'notes' => $notes]];
    }

    private function paymentWebhook(string $intentId, string $paymentId, int $amount): string
    {
        return json_encode(['data' => ['attributes' => ['type' => 'payment.paid', 'data' => ['id' => $paymentId, 'type' => 'payment', 'attributes' => ['payment_intent_id' => $intentId, 'amount' => $amount, 'currency' => 'PHP', 'status' => 'paid']]]]], JSON_THROW_ON_ERROR);
    }

    private function refundWebhook(array $refund): string
    {
        return json_encode(['data' => ['attributes' => ['type' => 'payment.refund.updated', 'data' => $refund]]], JSON_THROW_ON_ERROR);
    }

    private function postWebhook(string $body)
    {
        $timestamp = '1234567890';
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'refund-test-secret');
        return $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => 't='.$timestamp.',li='.$signature], $body);
    }
}
