<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_edit_profile_and_change_password_with_audit_event_without_password_data(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->get('/staff/profile')
            ->assertOk()
            ->assertSee('Profile information')
            ->assertSee('Change password');

        $this->put('/staff/profile', [
            'name' => 'Updated Staff Name',
            'email' => $staff->email,
            'phone' => '09171234567',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Updated Staff Name',
            'phone' => '09171234567',
        ]);

        $this->from('/staff/profile')
            ->put('/staff/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'NewSecurePassword-77',
                'password_confirmation' => 'NewSecurePassword-77',
            ])
            ->assertSessionHasErrors('current_password');

        $this->from('/staff/profile')
            ->put('/staff/profile/password', [
                'current_password' => 'OldSecurePassword-77',
                'password' => 'NewSecurePassword-77',
                'password_confirmation' => 'NewSecurePassword-77',
            ])
            ->assertRedirect();

        $staff->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword-77', $staff->password));
        $this->assertFalse(Hash::check('OldSecurePassword-77', $staff->password));
        $this->assertNotSame('NewSecurePassword-77', $staff->password);

        $log = \DB::table('activity_logs')
            ->where('user_id', $staff->id)
            ->where('action', 'password_changed')
            ->where('module', 'staff')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Updated Staff Name', $log->description);
        $this->assertStringNotContainsString('NewSecurePassword-77', $log->description);

        $adminSuffix = Str::lower(Str::random(10));
        $admin = User::create([
            'name' => 'Activity Log Admin',
            'username' => 'admin' . $adminSuffix,
            'email' => $adminSuffix . '@example.test',
            'password' => 'AdminSecurePassword-77',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->actingAs($admin)
            ->get('/admin/activity-logs')
            ->assertOk()
            ->assertSee('password_changed')
            ->assertSee('Updated Staff Name');
    }

    private function staff(): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'name' => 'Profile Test Staff',
            'username' => 'staff' . $suffix,
            'email' => $suffix . '@example.test',
            'password' => 'OldSecurePassword-77',
            'role' => 'staff',
            'status' => 'active',
        ]);
    }
}
