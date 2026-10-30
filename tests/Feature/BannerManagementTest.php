<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_and_manage_a_home_banner(): void
    {
        Storage::fake('public');
        $admin = User::create([
            'name' => 'Banner Admin',
            'email' => 'banner-admin@example.test',
            'username' => 'banneradmin',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.banners.store'), [
            'title' => 'Order Now',
            'headline' => 'Good Coffee Better Days',
            'description' => 'Browse freshly brewed drinks.',
            'button_text' => 'Shop now',
            'banner_type' => 'order_now',
            'destination' => 'menu',
            'audience' => 'all',
            'sort_order' => 1,
            'is_active' => '1',
            'image' => UploadedFile::fake()->createWithContent(
                'home-banner.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jV1sAAAAASUVORK5CYII=')
            ),
        ]);

        $response->assertRedirect(route('admin.banners.index'));
        $banner = Banner::query()->firstOrFail();
        $this->assertSame('order_now', $banner->banner_type);
        $this->assertSame('menu', $banner->destination);
        $this->assertSame('Browse freshly brewed drinks.', $banner->description);
        $this->assertSame('Shop now', $banner->button_text);
        Storage::disk('public')->assertExists($banner->image);
    }

    public function test_app_config_returns_live_banners_in_display_order(): void
    {
        $customer = User::create([
            'name' => 'Banner Customer',
            'email' => 'banner-customer@example.test',
            'username' => 'bannercustomer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);
        Banner::create([
            'title' => 'Second', 'headline' => 'Second banner', 'description' => 'Second description',
            'banner_type' => 'rewards', 'destination' => 'profile', 'audience' => 'all',
            'is_active' => true, 'sort_order' => 2,
        ]);
        Banner::create([
            'title' => 'First', 'headline' => 'First banner', 'description' => 'First description',
            'banner_type' => 'order_now', 'destination' => 'menu', 'audience' => 'all',
            'is_active' => true, 'sort_order' => 1,
        ]);
        Banner::create([
            'title' => 'Inactive', 'headline' => 'Hidden banner', 'audience' => 'all',
            'is_active' => false, 'sort_order' => 0,
        ]);
        foreach (range(3, 7) as $order) {
            Banner::create([
                'title' => "Announcement {$order}", 'headline' => "Headline {$order}",
                'banner_type' => 'announcement', 'destination' => 'announcement',
                'button_text' => 'Read more', 'audience' => 'all',
                'is_active' => true, 'sort_order' => $order,
            ]);
        }
        Banner::create([
            'title' => 'Future', 'headline' => 'Not live yet', 'audience' => 'all',
            'is_active' => true, 'sort_order' => 0, 'starts_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($customer, 'sanctum')->getJson('/api/v1/app-config');

        $response->assertOk()
            ->assertJsonPath('data.banners.0.title', 'First')
            ->assertJsonPath('data.banners.0.banner_type', 'order_now')
            ->assertJsonPath('data.banners.0.destination', 'menu')
            ->assertJsonPath('data.banners.0.description', 'First description')
            ->assertJsonPath('data.banners.1.title', 'Second')
            ->assertJsonCount(5, 'data.banners')
            ->assertJsonPath('data.banners.2.title', 'Announcement 3')
            ->assertJsonPath('data.banners.2.button_text', 'Read more');

        $this->getJson('/api/v1/home-banners')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.title', 'First')
            ->assertJsonPath('data.2.destination', 'announcement')
            ->assertJsonPath('data.2.button_text', 'Read more');
    }

    public function test_non_admin_cannot_manage_banners(): void
    {
        $staff = User::create([
            'name' => 'Banner Staff',
            'email' => 'banner-staff@example.test',
            'username' => 'bannerstaff',
            'password' => 'password',
            'role' => 'staff',
            'status' => 'active',
        ]);

        $this->actingAs($staff)
            ->get(route('admin.banners.index'))
            ->assertForbidden();
    }

    public function test_admin_can_edit_and_delete_a_promotional_announcement(): void
    {
        $admin = User::create([
            'name' => 'Banner Editor',
            'email' => 'banner-editor@example.test',
            'username' => 'bannereditor',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $banner = Banner::create([
            'title' => 'Coming soon', 'headline' => 'New coffee',
            'banner_type' => 'announcement', 'destination' => 'announcement',
            'audience' => 'all', 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.banners.update', $banner), [
                'title' => 'New Product', 'headline' => 'Try our new coffee',
                'description' => 'Available this week.', 'button_text' => 'See details',
                'banner_type' => 'new_product', 'destination' => 'announcement',
                'audience' => 'all', 'sort_order' => 4, 'is_active' => '1',
            ])
            ->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseHas('banners', [
            'id' => $banner->id, 'title' => 'New Product',
            'button_text' => 'See details', 'sort_order' => 4,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.banners.destroy', $banner))
            ->assertRedirect();

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
    }

    public function test_banner_can_link_to_a_specific_existing_promo_code(): void
    {
        $admin = User::create([
            'name' => 'Promo Banner Admin',
            'email' => 'promo-banner-admin@example.test',
            'username' => 'promobanneradmin',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $promo = PromoCode::create([
            'code' => 'JEPEE33', 'type' => 'percent', 'value' => 33,
            'per_user_limit' => 1, 'is_active' => true, 'show_in_app' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.banners.store'), [
                'title' => 'Promo Code', 'headline' => 'Use JEPEE33',
                'banner_type' => 'promo_code', 'destination' => 'promo_code',
                'destination_id' => $promo->id, 'button_text' => 'View promo',
                'audience' => 'all', 'sort_order' => 1, 'is_active' => '1',
            ])
            ->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseHas('banners', [
            'banner_type' => 'promo_code', 'destination' => 'promo_code',
            'destination_id' => $promo->id,
        ]);
    }
}
