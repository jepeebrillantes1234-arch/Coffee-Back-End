<?php

namespace Tests\Feature;

use App\Models\Banner;
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

        $response = $this->actingAs($customer, 'sanctum')->getJson('/api/v1/app-config');

        $response->assertOk()
            ->assertJsonPath('data.banners.0.title', 'First')
            ->assertJsonPath('data.banners.0.banner_type', 'order_now')
            ->assertJsonPath('data.banners.0.destination', 'menu')
            ->assertJsonPath('data.banners.0.description', 'First description')
            ->assertJsonPath('data.banners.1.title', 'Second')
            ->assertJsonCount(2, 'data.banners');
    }
}
