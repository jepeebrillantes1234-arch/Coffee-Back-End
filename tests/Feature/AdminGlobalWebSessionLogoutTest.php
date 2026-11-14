<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminGlobalWebSessionLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invalidate_all_database_web_sessions_and_the_current_session(): void
    {
        config(['session.driver' => 'database', 'session.table' => 'sessions']);
        $admin = $this->user('admin');
        $staff = $this->user('staff');
        $this->sessionRow('admin-session', $admin->id);
        $this->sessionRow('staff-session', $staff->id);

        $this->from('/admin/settings')->actingAs($admin)->post('/admin/security/sessions/invalidate-all')
            ->assertRedirect('/login');

        $this->assertGuest('web');
        $this->assertDatabaseMissing('sessions', ['id' => 'admin-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'staff-session']);
        $this->assertSame(0, DB::table('sessions')->whereNotNull('user_id')->count());
        $this->get('/login')->assertOk()->assertSee('All active web sessions were signed out.');
    }

    public function test_only_admins_can_open_global_web_session_logout(): void
    {
        $staff = $this->user('staff');
        $this->actingAs($staff)->post('/admin/security/sessions/invalidate-all')->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->post('/admin/security/sessions/invalidate-all')->assertRedirect('/login');
    }

    public function test_global_web_session_logout_is_not_available_for_non_database_driver(): void
    {
        config(['session.driver' => 'array']);
        $admin = $this->user('admin');
        $this->from('/admin/settings')->actingAs($admin)->post('/admin/security/sessions/invalidate-all')
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors('sessions');
        $this->assertAuthenticatedAs($admin, 'web');
    }

    private function sessionRow(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'feature-test',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
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
