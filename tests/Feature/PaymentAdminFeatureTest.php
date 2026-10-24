<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentAdminFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_admin_pages_are_admin_only_and_never_render_gateway_secrets(): void
    {
        config(['paymongo.mode' => 'paymongo', 'paymongo.secret' => 'do-not-render-this', 'shop.payment' => 'online']);
        $this->get('/admin/payments')->assertRedirect('/login');
        $this->actingAs($this->user('staff'))->get('/admin/payments')->assertForbidden();
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/admin/payments/settings')
            ->assertOk()->assertSee('PayMongo')->assertSee('Configured')->assertDontSee('do-not-render-this');
        $this->put('/admin/payments/settings', ['qrph_enabled' => '0'])->assertRedirect();
        $this->assertSame('0', Settings::get('payment_qrph_enabled'));
    }

    public function test_transaction_history_filters_existing_payment_records(): void
    {
        $admin = $this->user('admin');
        $customer = $this->user('customer');
        $matching = $this->order($customer, 'KJM-SEARCH-ME', 'qrph', 'unpaid');
        Payment::create(['order_id' => $matching->id, 'method' => 'qrph', 'provider' => 'paymongo', 'provider_payment_id' => 'pay_visible_123', 'amount' => 120, 'status' => 'pending']);
        $other = $this->order($customer, 'KJM-OTHER', 'cod', 'paid');
        Payment::create(['order_id' => $other->id, 'method' => 'cod', 'amount' => 60, 'status' => 'paid', 'paid_at' => now()]);
        $expiredOrder = $this->order($customer, 'KJM-EXPIRED', 'qrph', 'unpaid');
        $expiredOrder->update(['status' => 'cancelled']);
        Payment::create(['order_id' => $expiredOrder->id, 'method' => 'qrph', 'amount' => 45, 'status' => 'expired']);
        Payment::where('order_id', $expiredOrder->id)->update(['created_at' => now()->subDay()]);

        $this->actingAs($admin)->get('/admin/payments?q=SEARCH-ME&method=qrph&status=pending')
            ->assertOk()->assertSee('KJM-SEARCH-ME')->assertSee('pay_visible_123')->assertDontSee('KJM-OTHER');
        $this->actingAs($admin)->get('/admin/payments?method=cod&status=paid')
            ->assertOk()->assertSee('KJM-OTHER')->assertDontSee('KJM-SEARCH-ME');
        $this->actingAs($admin)->get('/admin/payments?status=expired')
            ->assertOk()->assertSee('KJM-EXPIRED')->assertDontSee('KJM-OTHER');
        $this->actingAs($admin)->get('/admin/payments?from=' . now()->toDateString())
            ->assertOk()->assertDontSee('KJM-EXPIRED');
        $this->actingAs($admin)->get('/admin/payments?from=' . now()->subDay()->toDateString() . '&to=' . now()->subDay()->toDateString())
            ->assertOk()->assertSee('KJM-EXPIRED');
    }

    public function test_transaction_history_uses_pagination(): void
    {
        $admin = $this->user('admin');
        $customer = $this->user('customer');
        $first = null;
        for ($i = 0; $i < 21; $i++) {
            $number = 'KJM-PAGE-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $order = $this->order($customer, $number, 'cash', 'unpaid');
            Payment::create(['order_id' => $order->id, 'method' => 'cash', 'amount' => 20, 'status' => 'pending']);
            if ($i === 0) $first = $number;
        }
        $this->actingAs($admin)->get('/admin/payments?page=2')->assertOk()->assertSee($first);
    }

    public function test_invalid_signature_and_incorrect_paid_amount_do_not_update_order(): void
    {
        config(['paymongo.webhook_secret' => 'unit-test-hook-secret']);
        $order = $this->order($this->user('customer'), 'KJM-SECURE-ORDER', 'qrph', 'unpaid');
        Payment::create(['order_id' => $order->id, 'method' => 'qrph', 'provider' => 'paymongo', 'amount' => 120, 'status' => 'pending']);

        $body = $this->webhook($order->order_number, 'pay_test', 11999);
        $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => 't=1234567890,li=invalid'], $body)->assertUnauthorized();
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->postWebhook($body)->assertOk();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->payments()->first()->status);
    }

    public function test_verified_payment_webhook_is_idempotent_and_transitions_once_to_paid(): void
    {
        config(['paymongo.webhook_secret' => 'unit-test-hook-secret']);
        $order = $this->order($this->user('customer'), 'KJM-ONCE-ONLY', 'qrph', 'unpaid');
        Payment::create(['order_id' => $order->id, 'method' => 'qrph', 'provider' => 'paymongo', 'amount' => 120, 'status' => 'pending']);
        $body = $this->webhook($order->order_number, 'pay_test_once', 12000);

        $this->postWebhook($body)->assertOk();
        $paidAt = $order->payments()->first()->fresh()->paid_at;
        $this->postWebhook($body)->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(1, $order->payments()->count());
        $this->assertEquals($paidAt, $order->payments()->first()->paid_at);
    }

    public function test_admin_cash_payment_transitions_from_unpaid_to_paid_and_refunded(): void
    {
        $admin = $this->user('admin');
        $order = $this->order($this->user('customer'), 'KJM-STATUS-CHANGE', 'cash', 'unpaid');
        Payment::create(['order_id' => $order->id, 'method' => 'cash', 'amount' => 120, 'status' => 'pending']);

        $this->actingAs($admin)->post('/admin/payments/' . $order->id . '/paid')->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('paid', $order->payments()->first()->status);

        $this->post('/admin/payments/' . $order->id . '/refund')->assertRedirect();
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame('refunded', $order->payments()->first()->status);
    }

    public function test_qrph_method_can_be_disabled_without_changing_cash_and_cod_options(): void
    {
        config(['paymongo.mode' => 'paymongo', 'paymongo.secret' => 'test-secret', 'shop.payment' => 'online']);
        Settings::set('payment_qrph_enabled', '0');
        $customer = $this->user('customer');
        $category = Category::create(['name' => 'Test Coffee']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Test Latte', 'base_price' => 120, 'is_available' => true]);
        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', [
            'order_type' => 'pickup', 'payment_method' => 'qrph',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonPath('success', false)->assertJsonPath('message', 'QRPh payment is currently unavailable.');
        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/app-config')
            ->assertOk()->assertJsonPath('data.settings.qrph_enabled', false)
            ->assertJsonPath('data.settings.available_payment_methods', ['cod']);
        $this->assertDatabaseCount('orders', 0);
    }

    private function postWebhook(string $body)
    {
        $timestamp = '1234567890';
        $sig = hash_hmac('sha256', $timestamp . '.' . $body, 'unit-test-hook-secret');
        return $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => 't=' . $timestamp . ',li=' . $sig,
        ], $body);
    }

    private function webhook(string $reference, string $paymentId, int $amount): string
    {
        return json_encode(['data' => ['attributes' => ['type' => 'checkout_session.payment.paid', 'data' => [
            'attributes' => ['reference_number' => $reference, 'payments' => [[
                'id' => $paymentId, 'attributes' => ['status' => 'paid', 'amount' => $amount],
            ]]],
        ]]]], JSON_THROW_ON_ERROR);
    }

    private function order(User $customer, string $number, string $method, string $paymentStatus): Order
    {
        // SQLite's baseline migration predates the MySQL-only QRPh enum alteration.
        $storedMethod = $method === 'qrph' ? 'cash' : $method;
        return Order::create([
            'order_number' => $number, 'user_id' => $customer->id, 'order_type' => $storedMethod === 'cod' ? 'delivery' : 'pickup',
            'status' => 'pending', 'subtotal' => 120, 'total' => 120,
            'payment_method' => $storedMethod, 'payment_status' => $paymentStatus,
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' test', 'username' => $role . Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(12)) . '@example.test', 'password' => 'password', 'role' => $role, 'status' => 'active',
        ]);
    }
}
