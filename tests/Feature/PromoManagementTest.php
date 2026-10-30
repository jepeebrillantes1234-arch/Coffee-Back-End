<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PromoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_promo_and_form_keeps_current_host(): void
    {
        Storage::fake('public');
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->get(route('admin.promos.create'))
            ->assertOk()
            ->assertSee('action="/admin/promos"', false)
            ->assertSee('name="_token"', false);

        $data = $this->promoForm('WELCOME50') + [
            'image' => UploadedFile::fake()->createWithContent(
                'promo.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jV1sAAAAASUVORK5CYII=')
            ),
        ];

        $this->actingAs($admin)
            ->post(route('admin.promos.store'), $data)
            ->assertRedirect(route('admin.promos.index'));

        $promo = PromoCode::where('code', 'WELCOME50')->firstOrFail();
        $this->assertSame('fixed', $promo->type);
        $this->assertEquals(50, (float) $promo->value);
        Storage::disk('public')->assertExists($promo->image);

        $this->actingAs($admin)
            ->put(route('admin.promos.update', $promo), array_merge($this->promoForm('WELCOME33'), ['value' => 33]))
            ->assertRedirect(route('admin.promos.index'));
        $this->assertDatabaseHas('promo_codes', ['id' => $promo->id, 'code' => 'WELCOME33', 'value' => 33]);

        $this->actingAs($admin)
            ->delete(route('admin.promos.destroy', $promo))
            ->assertRedirect();
        $this->assertDatabaseMissing('promo_codes', ['id' => $promo->id]);
    }

    public function test_promo_validation_rejects_invalid_percentage_and_image(): void
    {
        $admin = $this->user('admin');
        $invalid = array_merge($this->promoForm('TOOBIG'), ['type' => 'percent', 'value' => 101]);
        $this->actingAs($admin)
            ->post(route('admin.promos.store'), $invalid)
            ->assertSessionHasErrors('value');

        $this->actingAs($admin)
            ->post(route('admin.promos.store'), array_merge($this->promoForm('BADIMAGE'), [
                'image' => UploadedFile::fake()->create('not-an-image.txt', 4, 'text/plain'),
            ]))
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('promo_codes', ['code' => 'TOOBIG']);
    }

    public function test_promos_api_is_separate_from_banner_home_api(): void
    {
        $this->promo(['code' => 'LIVE10', 'sort_order' => 2]);
        $this->promo(['code' => 'INACTIVE', 'is_active' => false, 'sort_order' => 0]);
        $this->promo(['code' => 'HIDDEN', 'show_in_app' => false, 'sort_order' => 0]);
        $this->promo(['code' => 'FUTURE', 'starts_at' => now()->addDay(), 'sort_order' => 0]);
        $this->promo(['code' => 'EXPIRED', 'expires_at' => now()->subDay(), 'sort_order' => 0]);

        $this->getJson('/api/v1/promos')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'LIVE10');

        $this->promo(['code' => 'LIVE20', 'sort_order' => 1]);
        Banner::create([
            'title' => 'Order Now', 'headline' => 'Coffee time', 'banner_type' => 'order_now',
            'destination' => 'menu', 'audience' => 'all', 'is_active' => true, 'sort_order' => 0,
        ]);

        $home = $this->getJson('/api/v1/home-banners')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Order Now')
            ->assertJsonMissingPath('data.0.promo_id');
        $this->assertSame(['Order Now'], array_column($home->json('data'), 'title'));
    }

    public function test_home_endpoint_returns_only_the_five_sorted_live_banners(): void
    {
        foreach (range(1, 6) as $order) {
            Banner::create([
                'title' => "Banner {$order}", 'headline' => "Headline {$order}",
                'banner_type' => 'announcement', 'destination' => 'announcement',
                'audience' => 'all', 'is_active' => true, 'sort_order' => $order,
            ]);
        }
        $this->promo(['code' => 'PROMO-CARD-1', 'sort_order' => 0]);
        $this->getJson('/api/v1/home-banners')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.title', 'Banner 1')
            ->assertJsonPath('data.4.title', 'Banner 5')
            ->assertJsonMissingPath('data.0.promo_id');
    }

    public function test_checkout_calculates_discounts_on_backend_and_enforces_usage_limits(): void
    {
        $customer = $this->user('customer');
        $product = $this->product(200);
        $this->promo([
            'code' => 'JEPEE33', 'type' => 'percent', 'value' => 50,
            'min_order' => 100, 'max_discount' => 60, 'usage_limit' => 1,
            'per_user_limit' => 1,
        ]);
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'option_ids' => []]],
            'order_type' => 'pickup', 'payment_method' => 'cash', 'promo_code' => 'jepee33',
        ];

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.order.subtotal', 200)
            ->assertJsonPath('data.order.discount', 60)
            ->assertJsonPath('data.order.total', 140);

        $this->postJson('/api/v1/orders', $payload)->assertUnprocessable();
        $this->assertSame(1, Order::count());
        $this->assertDatabaseCount('promo_usages', 1);
    }

    public function test_jepee33_requires_two_hundred_and_uses_the_saved_discount_configuration(): void
    {
        $customer = $this->user('customer');
        $belowMinimumProduct = $this->product(199.99);
        $boundaryProduct = $this->product(200.00);
        $this->promo([
            'code' => 'JEPEE33', 'type' => 'percent', 'value' => 50,
            // Mirrors the local saved record before its min_order was aligned
            // with the required JEPEE33 threshold of ₱200.
            'min_order' => 100, 'is_active' => true, 'show_in_app' => true,
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/checkout/preview', [
                'items' => [['product_id' => $belowMinimumProduct->id, 'quantity' => 1]],
                'order_type' => 'pickup', 'promo_code' => 'JEPEE33',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.promo_code.0', 'JEPEE33 is valid only when the eligible product subtotal is at least ₱200.00.');

        $this->postJson('/api/v1/checkout/preview', [
            'items' => [['product_id' => $boundaryProduct->id, 'quantity' => 1]],
            'order_type' => 'pickup', 'promo_code' => 'jepee33',
        ])
            ->assertOk()
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.discount', 100)
            ->assertJsonPath('data.total', 100);

        // Applying the same promo for delivery works before an address is
        // selected; placing the order still requires the delivery address.
        $deliveryPayload = [
            'items' => [['product_id' => $boundaryProduct->id, 'quantity' => 1]],
            'order_type' => 'delivery', 'promo_code' => 'JEPEE33',
        ];
        $this->postJson('/api/v1/checkout/preview', $deliveryPayload)
            ->assertOk()
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.discount', 100);

        $this->postJson('/api/v1/orders', $deliveryPayload + ['payment_method' => 'cod'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_address');
    }

    public function test_checkout_rejects_inactive_and_expired_promo_codes(): void
    {
        $customer = $this->user('customer');
        $product = $this->product(300);
        $this->promo([
            'code' => 'INACTIVE', 'is_active' => false,
        ]);
        $this->promo([
            'code' => 'EXPIRED', 'expires_at' => now()->subMinute(),
        ]);

        foreach (['INACTIVE', 'EXPIRED'] as $code) {
            $this->actingAs($customer, 'sanctum')
                ->postJson('/api/v1/checkout/preview', [
                    'items' => [['product_id' => $product->id, 'quantity' => 1]],
                    'order_type' => 'pickup', 'promo_code' => $code,
                ])
                ->assertUnprocessable()
                ->assertJsonPath('errors.promo_code.0', 'Hindi valid o expired ang promo code.');
        }
    }

    private function promo(array $overrides = []): PromoCode
    {
        return PromoCode::create(array_merge([
            'code' => 'PROMO' . Str::upper(Str::random(5)), 'type' => 'fixed', 'value' => 20,
            'min_order' => 0, 'per_user_limit' => 1, 'is_active' => true,
            'show_in_app' => true, 'sort_order' => 0,
        ], $overrides));
    }

    private function promoForm(string $code): array
    {
        return [
            'code' => $code, 'type' => 'fixed', 'value' => 50,
            'min_order' => 0, 'per_user_limit' => 1, 'sort_order' => 0,
            'is_active' => '1', 'show_in_app' => '1',
        ];
    }

    private function product(float $price): Product
    {
        $category = Category::create(['name' => 'Promo Test']);
        return Product::create([
            'category_id' => $category->id, 'name' => 'Promo test coffee',
            'base_price' => $price, 'is_available' => true,
        ]);
    }

    private function user(string $role): User
    {
        $suffix = Str::lower(Str::random(10));
        return User::create([
            'name' => ucfirst($role) . ' Promo Test', 'email' => "$suffix@example.test",
            'username' => $role . $suffix, 'password' => 'password',
            'role' => $role, 'status' => 'active',
        ]);
    }
}
