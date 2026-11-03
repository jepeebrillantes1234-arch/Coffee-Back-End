<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LoyaltyTransaction;
use App\Models\Membership;
use App\Models\Address;
use App\Models\MembershipPayment;
use App\Models\HomePromo;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Services\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Support\Settings;
use Illuminate\Support\Str;
use Tests\TestCase;

class MembershipLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_membership_price_and_discount_settings(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/admin/memberships/settings')
            ->assertOk()
            ->assertSee('Membership Card Price');
        $this->actingAs($admin)->put('/admin/memberships/settings', [
            'membership_card_price' => '250.50',
            'membership_discount_percent' => '12.5',
            'membership_discount_enabled' => '1',
        ])->assertRedirect();

        $this->assertSame('250.50', Settings::get('membership_card_price'));
        $this->assertSame('12.50', Settings::get('membership_discount_percent'));
        $this->assertSame('1', Settings::get('membership_discount_enabled'));

        $this->actingAs($admin)->put('/admin/memberships/settings', [
            'membership_card_price' => '250.50',
            'membership_discount_percent' => '100.01',
            'membership_discount_enabled' => '1',
        ])->assertSessionHasErrors('membership_discount_percent');
        $this->assertSame('12.50', Settings::get('membership_discount_percent'));
    }

    public function test_future_membership_purchase_uses_new_price_and_preserves_paid_payment(): void
    {
        config(['paymongo.mode' => 'simulated', 'paymongo.public_url' => 'https://coffee.test']);
        Settings::set('membership_card_price', '250');
        $previousCustomer = $this->user('customer');
        $previousMembership = $this->membership($previousCustomer, 'active');
        $previousPayment = $this->payment($previousMembership);
        $previousPayment->update(['status' => 'paid', 'paid_at' => now()]);

        $futureCustomer = $this->user('customer');
        $this->actingAs($futureCustomer, 'sanctum')
            ->postJson('/api/v1/membership/checkout')
            ->assertCreated()
            ->assertJsonPath('data.payment.amount', '250.00');

        $this->assertSame('100.00', $previousPayment->fresh()->amount);
        $this->assertSame('paid', $previousPayment->fresh()->status);
    }

    public function test_active_verified_member_gets_discount_only_on_full_price_standalone_products(): void
    {
        Settings::set('first_order_enabled', '0');
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active');
        $this->paidMembershipPayment($membership);
        $regular = $this->product();
        $sale = $this->product();
        $sale->update(['discount_type' => 'percent', 'discount_value' => 20, 'discount_is_active' => true]);

        $payload = [
            'order_type' => 'pickup', 'payment_method' => 'cash',
            'items' => [
                ['product_id' => $regular->id, 'quantity' => 1],
                ['product_id' => $sale->id, 'quantity' => 1],
            ],
        ];
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout/preview', $payload)
            ->assertOk()
            ->assertJsonPath('data.subtotal', 180)
            ->assertJsonPath('data.membership_discount', 10)
            ->assertJsonPath('data.membership_discount_percent', 10)
            ->assertJsonPath('data.promo_discount', 0)
            ->assertJsonPath('data.total', 170);

        $payload['checkout_request_id'] = (string) Str::uuid();
        $this->postJson('/api/v1/orders', $payload)->assertCreated();
        $order = Order::where('user_id', $customer->id)->firstOrFail();
        $this->assertSame(10.0, (float) $order->membership_discount_amount);
        $this->assertSame(10.0, (float) $order->membership_discount_percent);
        $this->assertSame(170.0, (float) $order->total);
    }

    public function test_inactive_or_unverified_members_do_not_get_member_discount(): void
    {
        Settings::set('first_order_enabled', '0');
        $customer = $this->user('customer');
        $this->membership($customer, 'active'); // Active flag without a paid verification record.
        $product = $this->product();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout/preview', [
                'order_type' => 'pickup', 'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('data.membership_discount', 0);

        $inactiveCustomer = $this->user('customer');
        $inactive = $this->membership($inactiveCustomer, 'inactive');
        $this->paidMembershipPayment($inactive);
        $this->actingAs($inactiveCustomer, 'sanctum')
            ->postJson('/api/v1/checkout/preview', [
                'order_type' => 'pickup', 'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('data.membership_discount', 0);
    }

    public function test_bogo_discount_excludes_only_free_units_from_member_discount(): void
    {
        Settings::set('first_order_enabled', '0');
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active');
        $this->paidMembershipPayment($membership);
        $product = $this->product();
        $promo = PromoCode::create([
            'code' => 'TESTBOGO', 'type' => 'bogo', 'value' => 0, 'product_id' => $product->id,
            'buy_qty' => 1, 'free_qty' => 1, 'per_user_limit' => 1, 'is_active' => true,
            'show_in_app' => true,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout/preview', [
                'order_type' => 'pickup', 'promo_code' => $promo->code,
                'items' => [['product_id' => $product->id, 'quantity' => 2]],
            ])
            ->assertOk()
            ->assertJsonPath('data.promo_discount', 100)
            ->assertJsonPath('data.membership_discount', 10)
            ->assertJsonPath('data.total', 90);
    }

    public function test_member_discount_applies_to_regular_items_but_not_bundle_price(): void
    {
        Settings::set('first_order_enabled', '0');
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active');
        $this->paidMembershipPayment($membership);
        $regular = $this->product();
        $bundleProduct = $this->product();
        $bundle = HomePromo::create(['name' => 'Feature Test Bundle', 'price' => 150, 'is_active' => true]);
        $bundle->products()->attach($bundleProduct->id, ['quantity' => 2]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout/preview', [
                'order_type' => 'pickup',
                'items' => [['product_id' => $regular->id, 'quantity' => 1]],
                'home_promos' => [['home_promo_id' => $bundle->id, 'quantity' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 250)
            ->assertJsonPath('data.membership_discount', 10)
            ->assertJsonPath('data.total', 240);
    }

    public function test_global_percentage_promo_is_not_stacked_with_member_discount(): void
    {
        Settings::set('first_order_enabled', '0');
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active');
        $this->paidMembershipPayment($membership);
        $product = $this->product();
        $promo = PromoCode::create([
            'code' => 'TESTPERCENT', 'type' => 'percent', 'value' => 10,
            'per_user_limit' => 1, 'is_active' => true, 'show_in_app' => true,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout/preview', [
                'order_type' => 'pickup', 'promo_code' => $promo->code,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertOk()
            ->assertJsonPath('data.promo_discount', 10)
            ->assertJsonPath('data.membership_discount', 0)
            ->assertJsonPath('data.total', 90);
    }

    public function test_admin_configured_member_percentage_is_used_and_can_be_disabled(): void
    {
        Settings::set('first_order_enabled', '0');
        Settings::set('membership_discount_percent', '12.5');
        Settings::set('membership_discount_enabled', '1');
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active');
        $this->paidMembershipPayment($membership);
        $product = $this->product();
        $payload = ['order_type' => 'pickup', 'items' => [['product_id' => $product->id, 'quantity' => 1]]];

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout/preview', $payload)
            ->assertOk()
            ->assertJsonPath('data.membership_discount_percent', 12.5)
            ->assertJsonPath('data.membership_discount', 12.5)
            ->assertJsonPath('data.total', 87.5);

        Settings::set('membership_discount_enabled', '0');
        $this->postJson('/api/v1/checkout/preview', $payload)
            ->assertOk()
            ->assertJsonPath('data.membership_discount', 0)
            ->assertJsonPath('data.total', 100);
    }

    public function test_order_checkout_request_retry_returns_same_order_and_discount_snapshot(): void
    {
        Settings::set('first_order_enabled', '0');
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active');
        $this->paidMembershipPayment($membership);
        $product = $this->product();
        $payload = [
            'order_type' => 'pickup', 'payment_method' => 'cash', 'checkout_request_id' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        $first = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', $payload)->assertCreated();
        $second = $this->postJson('/api/v1/orders', $payload)->assertCreated();
        $this->assertSame($first->json('data.order.id'), $second->json('data.order.id'));
        $this->assertSame(1, Order::where('user_id', $customer->id)->count());
        $this->assertSame(10.0, (float) Order::where('user_id', $customer->id)->firstOrFail()->membership_discount_amount);

        $changedPayload = $payload;
        $changedPayload['items'][0]['quantity'] = 2;
        $this->postJson('/api/v1/orders', $changedPayload)->assertUnprocessable();
        $this->assertSame(1, Order::where('user_id', $customer->id)->count());
    }

    public function test_membership_checkout_stays_pending_until_verified_payment_of_exactly_one_hundred_pesos(): void
    {
        config(['paymongo.mode' => 'simulated', 'paymongo.public_url' => 'https://coffee.test']);

        $customer = $this->user('customer');
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/membership/checkout')
            ->assertCreated()
            ->assertJsonPath('data.payment.amount', '100.00')
            ->assertJsonPath('data.payment.status', 'pending');

        $membership = Membership::where('user_id', $customer->id)->firstOrFail();
        $this->assertSame('pending', $membership->status);
        $this->assertFalse($membership->isActive());

        $membership->payments()->update([
            'provider' => 'paymongo',
            'provider_session_id' => 'cs_membership_test',
            'checkout_url' => 'https://checkout.test/session',
        ]);

        Http::fake(['*' => Http::response([
            'data' => [
                'id' => 'cs_membership_test',
                'attributes' => ['payments' => [[
                    'id' => 'pay_membership_test',
                    'attributes' => ['status' => 'paid', 'amount' => 10000],
                ]]],
            ],
        ], 200)]);

        $this->postJson('/api/v1/membership/payment/verify')
            ->assertOk()
            ->assertJsonPath('data.membership.active', true);

        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame('paid', $membership->payments()->firstOrFail()->status);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'cs_membership_test'));
    }

    public function test_wrong_payment_amount_is_rejected_and_membership_remains_pending(): void
    {
        config(['paymongo.secret' => 'sk_test_only']);
        $customer = $this->user('customer');
        $membership = $this->membership($customer);
        $this->payment($membership, 'cs_wrong_amount');

        Http::fake(['*' => Http::response([
            'data' => [
                'id' => 'cs_wrong_amount',
                'attributes' => ['payments' => [[
                    'id' => 'pay_wrong_amount',
                    'attributes' => ['status' => 'paid', 'amount' => 9999],
                ]]],
            ],
        ], 200)]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/membership/payment/verify')
            ->assertStatus(503);

        $this->assertSame('pending', $membership->fresh()->status);
        $this->assertSame('pending', $membership->payments()->firstOrFail()->status);
    }

    public function test_forged_or_invalidly_signed_payment_callbacks_are_rejected(): void
    {
        config(['paymongo.webhook_secret' => 'test-webhook-secret']);
        $customer = $this->user('customer');
        $membership = $this->membership($customer);
        $payment = $this->payment($membership);

        $body = $this->paidWebhookBody($payment->payment_reference, 10000);
        $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't=123,li=forged',
        ], $body)->assertUnauthorized();

        $this->assertSame('pending', $membership->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_signed_webhook_with_wrong_amount_does_not_activate_membership(): void
    {
        config(['paymongo.webhook_secret' => 'test-webhook-secret']);
        $customer = $this->user('customer');
        $membership = $this->membership($customer);
        $payment = $this->payment($membership);

        $body = $this->paidWebhookBody($payment->payment_reference, 9999);
        $timestamp = '1234567890';
        $header = 't=' . $timestamp . ',li=' . hash_hmac('sha256', $timestamp . '.' . $body, 'test-webhook-secret');
        $gateway = app(PaymentGateway::class);
        $this->assertTrue($gateway->verifySignature($body, $header));

        try {
            $gateway->handleEvent(json_decode($body, true, 512, JSON_THROW_ON_ERROR));
            $this->fail('Expected mismatched payment amount to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('amount did not match', $exception->getMessage());
        }

        $this->assertSame('pending', $membership->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_completed_paid_order_awards_all_product_units_once(): void
    {
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active', 0);
        $product = $this->product();
        $order = Order::create([
            'order_number' => 'KJM-LOY-' . Str::upper(Str::random(8)),
            'user_id' => $customer->id,
            'order_type' => 'pickup',
            'status' => 'pending',
            'subtotal' => 1000,
            'total' => 1000,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 7,
            'unit_price' => 100,
            'total_price' => 700,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 3,
            'unit_price' => 100,
            'total_price' => 300,
        ]);

        // Exercise the normal model transition; OrderObserver awards points.
        $order->update(['status' => 'completed', 'payment_status' => 'paid']);

        $this->assertSame(10, (int) $membership->fresh()->points_balance);
        $this->assertSame(1, LoyaltyTransaction::where('order_id', $order->id)->where('type', 'earned')->count());
        $this->assertSame(0, app(LoyaltyService::class)->awardForOrder($order->fresh()));
        $this->assertSame(10, (int) $membership->fresh()->points_balance);
        $this->assertSame(1, LoyaltyTransaction::where('order_id', $order->id)->where('type', 'earned')->count());
    }

    public function test_redemption_requires_enough_points_and_repeated_request_key_is_idempotent(): void
    {
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active', 20);
        $reward = $this->reward(30);

        try {
            app(LoyaltyService::class)->redeem($customer, $reward, 'insufficient-points-key');
            $this->fail('Expected insufficient-points redemption to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('not have enough points', $exception->getMessage());
        }
        $this->assertSame(20, (int) $membership->fresh()->points_balance);
        $this->assertDatabaseCount('reward_redemptions', 0);

        $membership->update(['points_balance' => 50]);
        $reward->update(['points_cost' => 30, 'stock' => 5]);
        $service = app(LoyaltyService::class);
        $first = $service->redeem($customer, $reward, 'same-redemption-request-key');
        $retry = $service->redeem($customer, $reward, 'same-redemption-request-key');

        $this->assertSame($first->id, $retry->id);
        $this->assertSame(20, (int) $membership->fresh()->points_balance);
        $this->assertSame(4, (int) $reward->fresh()->stock);
        $this->assertDatabaseCount('reward_redemptions', 1);
        $this->assertSame(1, LoyaltyTransaction::where('redemption_id', $first->id)->count());
    }

    public function test_staff_can_claim_a_redemption_once_and_customer_cannot_claim_it(): void
    {
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active', 40);
        $reward = $this->reward(30);
        $redemption = app(LoyaltyService::class)->redeem($customer, $reward, 'staff-claim-request-key');

        $this->actingAs($customer)->post('/staff/rewards/verify', ['code' => $redemption->code])
            ->assertForbidden();
        $staff = $this->user('staff');
        $this->actingAs($staff)->post('/staff/rewards/verify', ['code' => $redemption->code])
            ->assertOk();
        $this->assertSame('claimed', $redemption->fresh()->status);

        $this->actingAs($staff)->post('/staff/rewards/verify', ['code' => $redemption->code])
            ->assertOk()
            ;
        $this->assertSame('claimed', $redemption->fresh()->status);
        $this->assertSame(10, (int) $membership->fresh()->points_balance);
    }

    public function test_customer_rewards_api_redeems_securely_and_only_returns_own_history(): void
    {
        $customer = $this->user('customer');
        $this->membership($customer, 'active', 40);
        $otherCustomer = $this->user('customer');
        $this->membership($otherCustomer, 'active', 40);
        $reward = $this->reward(30);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/rewards')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reward->id);

        $redeemed = $this->postJson('/api/v1/rewards/' . $reward->id . '/redeem', [
            'idempotency_key' => 'customer-reward-redemption-key',
        ])->assertCreated()->assertJsonPath('data.points_spent', 30);
        $code = $redeemed->json('data.code');
        $this->assertNotEmpty($code);

        $this->getJson('/api/v1/rewards/redemptions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', $code);

        $this->actingAs($otherCustomer, 'sanctum')
            ->getJson('/api/v1/rewards/redemptions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_competing_redemption_requests_cannot_spend_the_same_points_twice(): void
    {
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active', 30);
        $reward = $this->reward(30);
        $service = app(LoyaltyService::class);

        $service->redeem($customer, $reward, 'competing-redemption-request-1');
        try {
            $service->redeem($customer, $reward, 'competing-redemption-request-2');
            $this->fail('A second redemption must not spend a balance that has already been consumed.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('not have enough points', $exception->getMessage());
        }

        $this->assertSame(0, (int) $membership->fresh()->points_balance);
        $this->assertDatabaseCount('reward_redemptions', 1);
        $this->assertSame(1, LoyaltyTransaction::where('type', 'redeemed')->count());
    }

    public function test_product_redemption_creates_separate_cod_delivery_fee_and_restores_points_once_on_cancel(): void
    {
        Settings::set('shop_lat', '15.950571');
        Settings::set('shop_lng', '120.538425');
        Settings::set('delivery_base_fee', '30');
        Settings::set('delivery_fee_per_km', '10');
        Settings::set('delivery_radius_km', '8');
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'active', 80);
        $product = $this->product();
        $reward = Reward::create([
            'name' => 'Redeemable Latte', 'reward_type' => 'free_product', 'product_id' => $product->id,
            'points_cost' => 30, 'stock' => 2, 'is_active' => true,
        ]);
        $address = Address::create([
            'user_id' => $customer->id, 'label' => 'Home', 'full_address' => 'Labit Proper, Urdaneta City',
            'latitude' => 15.950571, 'longitude' => 120.538425,
        ]);

        $this->actingAs($customer, 'sanctum')->postJson("/api/v1/rewards/{$reward->id}/delivery-quote", ['address_id' => $address->id])
            ->assertOk()->assertJsonPath('data.delivery_fee', 30);
        $created = $this->postJson("/api/v1/rewards/{$reward->id}/redeem", [
            'idempotency_key' => 'delivery-redemption-order-key', 'fulfillment_type' => 'delivery', 'address_id' => $address->id,
        ])->assertCreated()->assertJsonPath('data.delivery_fee', '30.00');

        $orderId = (int) $created->json('data.order_id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId, 'order_type' => 'delivery', 'subtotal' => 0, 'total' => 30,
            'delivery_fee' => 30, 'payment_method' => 'cod', 'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseHas('order_items', ['order_id' => $orderId, 'quantity' => 1, 'unit_price' => 0, 'total_price' => 0]);
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'method' => 'cod', 'amount' => 30, 'status' => 'pending']);
        $this->assertSame(50, (int) $membership->fresh()->points_balance);

        $redemptionId = (int) $created->json('data.id');
        $this->postJson("/api/v1/rewards/redemptions/{$redemptionId}/cancel")->assertOk();
        $this->assertSame(80, (int) $membership->fresh()->points_balance);
        $this->assertSame(2, (int) $reward->fresh()->stock);
        $this->postJson("/api/v1/rewards/redemptions/{$redemptionId}/cancel")->assertStatus(409);
        $this->assertSame(80, (int) $membership->fresh()->points_balance);
        $this->assertSame(1, LoyaltyTransaction::where('redemption_id', $redemptionId)->where('type', 'adjustment')->count());
    }

    public function test_admin_can_upload_and_activate_front_and_back_card_artwork_for_the_customer_app(): void
    {
        Storage::fake('public');
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/admin/settings')->assertOk()
            ->assertSee('Front Design')->assertSee('Back Design')
            ->assertSee('No front design uploaded')->assertSee('URL.createObjectURL');
        $this->put('/admin/settings', [
            'first_order_percent' => 15, 'first_order_max' => 100,
            'shop_lat' => 15.95, 'shop_lng' => 120.53,
            'delivery_base_fee' => 30, 'delivery_fee_per_km' => 10,
            'delivery_radius_km' => 8, 'auto_cancel_minutes' => 10,
            'membership_card_design_active' => 1,
            'membership_card_front_image' => UploadedFile::fake()->createWithContent(
                'member-card-front.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/n7sAAAAASUVORK5CYII='),
            ),
            'membership_card_back_image' => UploadedFile::fake()->createWithContent(
                'member-card-back.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/n7sAAAAASUVORK5CYII='),
            ),
        ])->assertRedirect();

        $frontPath = Settings::get('membership_card_front_design');
        $backPath = Settings::get('membership_card_back_design');
        $this->assertNotEmpty($frontPath);
        $this->assertNotEmpty($backPath);
        $this->assertSame($frontPath, Settings::get('membership_card_design'));
        Storage::disk('public')->assertExists($frontPath);
        Storage::disk('public')->assertExists($backPath);
        $customer = $this->user('customer');
        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/loyalty')
            ->assertOk()
            ->assertJsonPath('data.card_design_url', Storage::disk('public')->url($frontPath))
            ->assertJsonPath('data.card_design_front_url', Storage::disk('public')->url($frontPath))
            ->assertJsonPath('data.card_design_back_url', Storage::disk('public')->url($backPath));

        $this->actingAs($admin)->put('/admin/settings', [
            'first_order_percent' => 15, 'first_order_max' => 100,
            'shop_lat' => 15.95, 'shop_lng' => 120.53,
            'delivery_base_fee' => 30, 'delivery_fee_per_km' => 10,
            'delivery_radius_km' => 8, 'auto_cancel_minutes' => 10,
            'membership_card_design_active' => 1,
            'membership_card_front_image' => UploadedFile::fake()->createWithContent(
                'member-card-front-replacement.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/n7sAAAAASUVORK5CYII='),
            ),
        ])->assertRedirect();
        $replacementFrontPath = Settings::get('membership_card_front_design');
        $this->assertNotSame($frontPath, $replacementFrontPath);
        Storage::disk('public')->assertMissing($frontPath);
        Storage::disk('public')->assertExists($replacementFrontPath);
        $this->assertSame($backPath, Settings::get('membership_card_back_design'));

        $this->actingAs($admin)->get('/admin/settings')->assertOk()
            ->assertSee('/storage/' . $replacementFrontPath)->assertSee('/storage/' . $backPath);
    }
    public function test_customer_staff_and_admin_permissions_and_admin_pages(): void
    {
        $customer = $this->user('customer');
        $this->getJson('/api/v1/loyalty')->assertUnauthorized();
        $this->getJson('/api/v1/rewards')->assertUnauthorized();
        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/loyalty')->assertOk();

        $this->actingAs($customer)->get('/admin/memberships')->assertForbidden();
        $this->actingAs($customer)->get('/staff/rewards/verify')->assertForbidden();

        $staff = $this->user('staff');
        $this->actingAs($staff)->get('/staff/rewards/verify')->assertOk();
        $this->actingAs($staff)->get('/admin/memberships')->assertForbidden();

        $admin = $this->user('admin');
        $membership = $this->membership($customer, 'active', 12);
        $membership->payments()->create([
            'provider' => 'paymongo',
            'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/memberships')
            ->assertOk()
            ->assertSee($customer->name)
            ->assertSee('KJM-' . substr($membership->member_id, 4));
        $this->get('/admin/rewards/create')->assertOk();
        $this->get('/staff/rewards/verify')->assertOk();

        $this->post('/admin/rewards', [
            'name' => 'Free Test Coffee',
            'description' => 'Feature test reward',
            'reward_type' => 'other',
            'points_cost' => 30,
            'stock' => 3,
            'is_active' => 1,
        ])->assertRedirect('/admin/rewards');

        $reward = Reward::where('name', 'Free Test Coffee')->firstOrFail();
        $this->assertDatabaseHas('rewards', [
            'name' => 'Free Test Coffee',
            'points_cost' => 30,
            'is_active' => 1,
        ]);

        $this->put('/admin/rewards/' . $reward->id, [
            'name' => 'Updated Test Coffee',
            'description' => 'Updated feature test reward',
            'reward_type' => 'other',
            'points_cost' => 35,
            'stock' => 4,
            'is_active' => 1,
        ])->assertRedirect('/admin/rewards');
        $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'name' => 'Updated Test Coffee', 'points_cost' => 35]);

$this->from('/admin/rewards')->post('/admin/rewards/' . $reward->id . '/toggle')->assertRedirect('/admin/rewards');
        $this->assertDatabaseHas('rewards', ['id' => $reward->id, 'is_active' => 0]);

$this->from('/admin/rewards')->delete('/admin/rewards/' . $reward->id)->assertRedirect('/admin/rewards');
        $this->assertDatabaseMissing('rewards', ['id' => $reward->id]);
    }

    public function test_existing_paymongo_qrph_order_checkout_uses_mocked_gateway_and_keeps_qrph_method(): void
    {
        config([
            'paymongo.mode' => 'paymongo',
            'paymongo.secret' => 'sk_test_only',
            'paymongo.public_key' => 'pk_test_only',
            'paymongo.public_url' => 'https://coffee.test',
        ]);
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/payment_intents')) {
                return Http::response(['data' => ['id' => 'pi_qrph_test', 'attributes' => [
                    'client_key' => 'pi_qrph_test_client_key', 'status' => 'awaiting_payment_method',
                ]]], 201);
            }
            if (str_ends_with($request->url(), '/payment_methods')) {
                return Http::response(['data' => ['id' => 'pm_qrph_test']], 201);
            }
            if (str_ends_with($request->url(), '/pi_qrph_test/attach')) {
                return Http::response(['data' => ['attributes' => [
                    'status' => 'awaiting_next_action',
                    'next_action' => ['code' => ['image_url' => 'data:image/png;base64,dGVzdA==']],
                ]]], 200);
            }
            return Http::response(['errors' => [['code' => 'unexpected_mock_request']]], 500);
        });

        $customer = $this->user('customer');
        $order = Order::create([
            'order_number' => 'KJM-QR-' . Str::upper(Str::random(8)),
            'user_id' => $customer->id,
            'order_type' => 'pickup',
            'status' => 'awaiting_payment',
            'subtotal' => 160,
            'total' => 160,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        $result = app(PaymentGateway::class)->createCheckout($order);

        $this->assertSame('data:image/png;base64,dGVzdA==', $result['qr_image_url']);
        $this->assertSame($order->order_number, $result['payment_reference']);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qrph',
            'provider' => 'paymongo',
            'provider_intent_id' => 'pi_qrph_test',
            'status' => 'pending',
            'amount' => 160,
        ]);
        Http::assertSent(fn ($request) =>
            str_contains($request->url(), '/v1/payment_intents')
            && data_get($request->data(), 'data.attributes.payment_method_allowed.0') === 'qrph'
            && data_get($request->data(), 'data.attributes.description') === 'Order ' . $order->order_number
        );
    }

    public function test_valid_signed_membership_webhook_activates_after_exact_payment(): void
    {
        config(['paymongo.webhook_secret' => 'test-webhook-secret']);
        $customer = $this->user('customer');
        $membership = $this->membership($customer);
        $payment = $this->payment($membership);

        $body = $this->paidWebhookBody($payment->payment_reference, 10000);
        $timestamp = '1234567891';
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-webhook-secret');

        $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't=' . $timestamp . ',li=' . $signature,
        ], $body)->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_existing_signed_qrph_order_webhook_still_marks_order_paid(): void
    {
        config(['paymongo.webhook_secret' => 'test-webhook-secret']);
        $customer = $this->user('customer');
        $order = Order::create([
            'order_number' => 'KJM-QR-' . Str::upper(Str::random(8)),
            'user_id' => $customer->id,
            'order_type' => 'pickup',
            'status' => 'pending',
            'subtotal' => 160,
            'total' => 160,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        $body = $this->paidWebhookBody($order->order_number, 16000);
        $timestamp = '1234567892';
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-webhook-secret');

        $this->call('POST', '/api/v1/webhooks/paymongo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => 't=' . $timestamp . ',li=' . $signature,
        ], $body)->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'qrph',
            'provider' => 'paymongo',
            'status' => 'paid',
            'amount' => 160,
        ]);
    }
    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Test',
            'username' => $role . Str::lower(Str::random(8)),
            'email' => Str::lower(Str::random(12)) . '@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function membership(User $customer, string $status = 'pending', int $points = 0): Membership
    {
        return Membership::create([
            'user_id' => $customer->id,
            'member_id' => 'KJM-' . Str::upper(Str::random(10)),
            'status' => $status,
            'points_balance' => $points,
        ]);
    }

    private function payment(Membership $membership, ?string $session = null): MembershipPayment
    {
        return $membership->payments()->create([
            'provider' => 'paymongo',
            'provider_session_id' => $session,
            'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'pending',
        ]);
    }

    private function paidMembershipPayment(Membership $membership): MembershipPayment
    {
        return $membership->payments()->create([
            'provider' => 'simulated',
            'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    private function reward(int $cost): Reward
    {
        return Reward::create([
            'name' => 'Feature Test Reward',
            'description' => 'Test reward',
            'reward_type' => 'other',
            'points_cost' => $cost,
            'stock' => 5,
            'is_active' => true,
        ]);
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Test Coffee', 'is_active' => true]);
        return Product::create([
            'category_id' => $category->id,
            'name' => 'Test Latte',
            'base_price' => 100,
            'is_available' => true,
        ]);
    }

    private function paidWebhookBody(string $reference, int $amount): string
    {
        return json_encode([
            'data' => ['attributes' => [
                'type' => 'checkout_session.payment.paid',
                'data' => ['id' => 'cs_test', 'attributes' => [
                    'reference_number' => $reference,
                    'payments' => [[
                        'id' => 'pay_test',
                        'attributes' => ['status' => 'paid', 'amount' => $amount],
                    ]],
                ]],
            ]],
        ], JSON_THROW_ON_ERROR);
    }
}
