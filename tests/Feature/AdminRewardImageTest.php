<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRewardImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $admin = User::create([
            'name' => 'Rewards Admin',
            'username' => 'rewards-admin-' . Str::lower(Str::random(6)),
            'email' => Str::lower(Str::random(10)) . '@example.test',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->actingAs($admin);
    }

    public function test_reward_image_url_normalizes_legacy_paths_and_returns_null_for_missing_files(): void
    {
        Storage::disk('public')->put('rewards/present.jpg', 'image-bytes');

        $reward = new Reward(['name' => 'Test Reward', 'image' => 'storage/rewards/present.jpg']);
        $this->assertSame(url('/storage/rewards/present.jpg'), $reward->image_url);

        $reward->image = 'rewards/missing.jpg';
        $this->assertNull($reward->image_url);
    }

    public function test_reward_image_url_uses_current_request_host_instead_of_app_url(): void
    {
        Storage::disk('public')->put('rewards/beef-nachos.jpg', 'image-bytes');
        Reward::create([
            'name' => 'Beef Nachos',
            'image' => 'rewards/beef-nachos.jpg',
            'reward_type' => 'other',
            'points_cost' => 25,
            'is_active' => true,
        ]);
        config(['app.url' => 'https://configured-tunnel.example.test']);

        $this->get('http://127.0.0.1:8000/admin/rewards')
            ->assertOk()
            ->assertSee('http://127.0.0.1:8000/storage/rewards/beef-nachos.jpg', false)
            ->assertDontSee('https://configured-tunnel.example.test/storage/rewards/beef-nachos.jpg', false);
    }

    public function test_rewards_list_and_edit_form_show_a_fallback_for_missing_images(): void
    {
        $reward = Reward::create([
            'name' => 'Unavailable Image Reward',
            'image' => 'rewards/missing.jpg',
            'reward_type' => 'other',
            'points_cost' => 25,
            'is_active' => true,
        ]);

        $this->get('/admin/rewards')->assertOk()->assertSee('Reward image unavailable');
        $this->get('/admin/rewards/' . $reward->id . '/edit')->assertOk()->assertSee('Reward image unavailable');
    }

    public function test_admin_uploads_new_reward_image_and_replacement_keeps_previous_file(): void
    {
        $this->post('/admin/rewards', [
            'name' => 'Uploaded Reward',
            'description' => 'Image upload test',
            'reward_type' => 'other',
            'points_cost' => 25,
            'is_active' => 1,
            'image' => UploadedFile::fake()->createWithContent('new-reward.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jz7sAAAAASUVORK5CYII=')),
        ])->assertRedirect('/admin/rewards');

        $reward = Reward::where('name', 'Uploaded Reward')->firstOrFail();
        Storage::disk('public')->assertExists($reward->image);
        $previousPath = $reward->image;

        $this->put('/admin/rewards/' . $reward->id, [
            'name' => $reward->name,
            'reward_type' => 'other',
            'points_cost' => 25,
            'is_active' => 1,
            'image' => UploadedFile::fake()->createWithContent('replacement.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jz7sAAAAASUVORK5CYII=')),
        ])->assertRedirect('/admin/rewards');

        Storage::disk('public')->assertExists($previousPath);
        $updated = $reward->fresh();
        Storage::disk('public')->assertExists($updated->image);
        $this->get('/admin/rewards')->assertOk()->assertSee('object-cover')->assertSee($updated->image_url, false);
        $this->get('/admin/rewards/' . $updated->id . '/edit')->assertOk()->assertSee($updated->image_url, false);
    }
}
