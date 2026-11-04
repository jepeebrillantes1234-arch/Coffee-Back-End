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

    public function test_admin_can_manage_membership_and_promo_popups_using_existing_banner_fields(): void
    {
        $admin = User::create([
            'name' => 'Popup Admin',
            'email' => 'popup-admin@example.test',
            'username' => 'popupadmin',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        foreach ([
            ['membership', 'Membership Card Offer', 'membership'],
            ['promo', 'Coffee Promo', 'promos'],
        ] as [$type, $headline, $destination]) {
            $this->actingAs($admin)->post(route('admin.banners.store'), [
                'title' => $headline,
                'headline' => $headline,
                'description' => "Details for {$headline}",
                'button_text' => 'View offer',
                'banner_type' => $type,
                'destination' => $destination,
                'audience' => 'all',
                'sort_order' => 1,
                'section' => 'popups',
                'is_active' => '1',
            ])->assertRedirect(route('admin.banners.index', ['section' => 'popups']));
        }

        $this->assertDatabaseHas('banners', [
            'banner_type' => 'membership',
            'destination' => 'membership',
            'show_as_popup' => true,
            'button_text' => 'View offer',
        ]);
        $this->assertDatabaseHas('banners', [
            'banner_type' => 'promo',
            'show_as_popup' => true,
            'button_text' => 'View offer',
        ]);
    }

    public function test_app_config_returns_only_live_enabled_membership_and_promo_popups_in_priority_order(): void
    {
        $customer = User::create([
            'name' => 'Popup Customer',
            'email' => 'popup-customer@example.test',
            'username' => 'popupcustomer',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        Banner::create([
            'title' => 'Membership', 'headline' => 'Membership offer',
            'banner_type' => 'membership', 'destination' => 'membership',
            'button_text' => 'View membership', 'audience' => 'all',
            'is_active' => true, 'show_as_popup' => true, 'sort_order' => 2,
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
        ]);
        Banner::create([
            'title' => 'Promo', 'headline' => 'Coffee promotion',
            'banner_type' => 'promo', 'destination' => 'promos',
            'button_text' => 'View promo', 'audience' => 'all',
            'is_active' => true, 'show_as_popup' => true, 'sort_order' => 1,
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
        ]);
        Banner::create([
            'title' => 'Carousel only', 'headline' => 'Existing banner',
            'banner_type' => 'promo', 'destination' => 'promos',
            'audience' => 'all', 'is_active' => true,
            'show_as_popup' => false, 'sort_order' => 0,
        ]);
        Banner::create([
            'title' => 'Inactive', 'headline' => 'Inactive popup',
            'banner_type' => 'promo', 'destination' => 'promos',
            'audience' => 'all', 'is_active' => false,
            'show_as_popup' => true,
        ]);
        Banner::create([
            'title' => 'Future', 'headline' => 'Future popup',
            'banner_type' => 'membership', 'destination' => 'profile',
            'audience' => 'all', 'is_active' => true,
            'show_as_popup' => true, 'starts_at' => now()->addHour(),
        ]);
        Banner::create([
            'title' => 'Expired', 'headline' => 'Expired popup',
            'banner_type' => 'promo', 'destination' => 'promos',
            'audience' => 'all', 'is_active' => true,
            'show_as_popup' => true, 'ends_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/app-config');

        $response->assertOk()
            ->assertJsonCount(2, 'data.popup_promotions')
            ->assertJsonPath('data.popup_promotions.0.title', 'Promo')
            ->assertJsonPath('data.popup_promotions.0.show_as_popup', true)
            ->assertJsonPath('data.popup_promotions.1.title', 'Membership')
            ->assertJsonPath('data.popup_promotions.1.destination', 'membership')
            ->assertJsonCount(1, 'data.banners')
            ->assertJsonPath('data.banners.0.title', 'Carousel only');
    }


    public function test_admin_banner_sections_list_only_their_own_records(): void
    {
        $admin = User::create([
            'name' => 'Banner Sections Admin',
            'email' => 'banner-sections@example.test',
            'username' => 'bannersections',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        Banner::create([
            'title' => 'Carousel only', 'headline' => 'Home Banner Record',
            'banner_type' => 'order_now', 'destination' => 'menu',
            'audience' => 'all', 'is_active' => true, 'show_as_popup' => false,
        ]);
        Banner::create([
            'title' => 'Popup only', 'headline' => 'Popup Promotion Record',
            'banner_type' => 'membership', 'destination' => 'membership',
            'button_text' => 'View membership', 'audience' => 'all',
            'is_active' => true, 'show_as_popup' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.banners.index', ['section' => 'home']))
            ->assertOk()
            ->assertSee('Home Banner Record')
            ->assertDontSee('Popup Promotion Record');

        $this->actingAs($admin)
            ->get(route('admin.banners.index', ['section' => 'popups']))
            ->assertOk()
            ->assertSee('Popup Promotion Record')
            ->assertDontSee('Home Banner Record');
    }

    public function test_banner_form_submits_section_and_create_edit_keep_records_in_their_section(): void
    {
        $admin = User::create([
            'name' => 'Banner Section Editor',
            'email' => 'banner-section-editor@example.test',
            'username' => 'bannersectioneditor',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);

        foreach (['home', 'popups'] as $section) {
            $form = $this->actingAs($admin)->get(route('admin.banners.create', ['section' => $section]));
            $form->assertOk();
            $html = $form->getContent();
            $formStart = strpos($html, '<form method="POST"');
            $sectionInput = strpos($html, 'name="section"');
            $formEnd = strpos($html, '</form>');
            $this->assertNotFalse($formStart);
            $this->assertNotFalse($sectionInput);
            $this->assertNotFalse($formEnd);
            $this->assertGreaterThan($formStart, $sectionInput);
            $this->assertLessThan($formEnd, $sectionInput);

            $isPopup = $section === 'popups';
            $title = $isPopup ? 'Editable Popup' : 'Editable Home Banner';
            $attributes = [
                'title' => $title,
                'headline' => $title,
                'description' => 'Section regression coverage.',
                'button_text' => 'View details',
                'banner_type' => $isPopup ? 'membership' : 'order_now',
                'destination' => $isPopup ? 'membership' : 'menu',
                'audience' => 'all',
                'sort_order' => 1,
                'section' => $section,
                'is_active' => '1',
            ];

            $this->actingAs($admin)->post(route('admin.banners.store'), $attributes)
                ->assertRedirect(route('admin.banners.index', $isPopup ? ['section' => 'popups'] : []));
            $banner = Banner::query()->where('title', $title)->firstOrFail();
            $this->assertSame($isPopup, (bool) $banner->show_as_popup);

            $this->actingAs($admin)->get(route('admin.banners.edit', $banner))->assertOk()
                ->assertSee('name="section"', false);
            $this->actingAs($admin)->put(route('admin.banners.update', $banner), $attributes)
                ->assertRedirect(route('admin.banners.index', $isPopup ? ['section' => 'popups'] : []));
            $this->assertSame($isPopup, (bool) $banner->fresh()->show_as_popup);

            $listing = $this->actingAs($admin)->get(route('admin.banners.index', ['section' => $section]));
            $listing->assertOk()->assertSee($title);
            $otherSection = $section === 'popups' ? 'home' : 'popups';
            $this->actingAs($admin)->get(route('admin.banners.index', ['section' => $otherSection]))
                ->assertDontSee($title);
        }

        $homeBanner = Banner::query()->where('title', 'Editable Home Banner')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.banners.update', $homeBanner), [
            'title' => 'Moved Popup',
            'headline' => 'Moved Popup',
            'description' => 'Intentionally moved to Popup Promotions.',
            'button_text' => 'View membership',
            'banner_type' => 'membership',
            'destination' => 'membership',
            'audience' => 'all',
            'sort_order' => 1,
            'section' => 'popups',
            'is_active' => '1',
        ])->assertRedirect(route('admin.banners.index', ['section' => 'popups']));

        $this->assertDatabaseHas('banners', ['title' => 'Moved Popup', 'show_as_popup' => true]);
    }

}
