<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AccountLoginSecurity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountLoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'name' => 'Security Test Customer',
            'username' => 'security'.$suffix,
            'email' => $suffix.'@example.test',
            'password' => 'Correct-password-123',
            'role' => 'customer',
            'status' => 'active',
        ]);
    }

    private function apiLogin(User $user, string $password)
    {
        return $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => $password,
            'device_name' => 'security-test-device',
        ]);
    }

    public function test_web_and_mobile_password_login_share_account_failure_counter(): void
    {
        $user = $this->customer();

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-one'])
            ->assertRedirect('/login');
        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong-two'])
            ->assertRedirect('/login');

        $response = $this->apiLogin($user, 'wrong-three')
            ->assertStatus(429)
            ->assertJsonPath('data.security.status', 'locked')
            ->assertJsonPath('data.security.lockout_duration_seconds', 60)
            ->assertJsonPath('data.security.lockout_cycle', 1);
        $remaining = (int) $response->json('data.security.retry_after_seconds');
        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(60, $remaining);
        $this->assertSame($remaining, (int) $response->json('data.security.remaining_seconds'));
        $response->assertHeader('Retry-After', (string) $remaining);

        $this->assertSame(1, $user->fresh()->login_lockout_cycles);
    }

    public function test_api_lockout_expires_and_successful_login_clears_counters(): void
    {
        $user = $this->customer();
        $this->apiLogin($user, 'wrong-one')->assertUnauthorized();
        $this->apiLogin($user, 'wrong-two')->assertUnauthorized();
        $this->apiLogin($user, 'wrong-three')->assertStatus(429);

        $this->travel(1)->minutes();
        $this->apiLogin($user, 'Correct-password-123')
            ->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertSame(0, $user->login_lockout_cycles);
        $this->assertNull($user->login_locked_until);
        $this->assertFalse($user->password_reset_required);
        $this->assertCount(1, $user->tokens);
    }

    public function test_progressive_lockout_durations_and_final_reset_requirement(): void
    {
        $user = $this->customer();
        $durations = [60, 120, 300];

        foreach ($durations as $index => $duration) {
            $this->apiLogin($user, 'wrong-one')->assertUnauthorized();
            $this->apiLogin($user, 'wrong-two')->assertUnauthorized();
            $response = $this->apiLogin($user, 'wrong-three')
                ->assertStatus(429)
                ->assertJsonPath('data.security.status', 'locked')
                ->assertJsonPath('data.security.lockout_duration_seconds', $duration)
                ->assertJsonPath('data.security.lockout_cycle', $index + 1);
            $remaining = (int) $response->json('data.security.retry_after_seconds');
            $this->assertGreaterThan(0, $remaining);
            $this->assertLessThanOrEqual($duration, $remaining);

            $user->refresh();
            $this->assertNotNull($user->login_locked_until);
            $this->travel($duration)->seconds();
        }

        $this->apiLogin($user, 'wrong-after-final-lockout')
            ->assertStatus(429)
            ->assertJsonPath('data.security.status', 'password_reset_required')
            ->assertJsonPath('data.security.password_reset_required', true);
        $this->assertTrue($user->fresh()->password_reset_required);
    }

    public function test_status_endpoint_returns_active_lockout_and_hides_unknown_account_existence(): void
    {
        $locked = $this->customer();
        $unlocked = $this->customer();
        $this->apiLogin($locked, 'wrong-one')->assertUnauthorized();
        $this->apiLogin($locked, 'wrong-two')->assertUnauthorized();
        $this->apiLogin($locked, 'wrong-three')->assertStatus(429);

        $this->postJson('/api/v1/auth/lockout-status', ['login' => $locked->email])
            ->assertOk()
            ->assertJsonPath('data.security.status', 'locked')
            ->assertJsonPath('data.security.lockout_cycle', 1)
            ->assertJsonPath('data.security.lockout_duration_seconds', 60)
            ->assertJsonStructure(['data' => ['security' => ['locked_until', 'retry_after_seconds', 'remaining_seconds', 'lockout_duration_seconds', 'lockout_cycle']]]);

        $known = $this->postJson('/api/v1/auth/lockout-status', ['login' => $unlocked->email])
            ->assertOk()->json('data.security');
        $unknown = $this->postJson('/api/v1/auth/lockout-status', ['login' => 'missing@example.test'])
            ->assertOk()->json('data.security');
        $this->assertSame($unknown, $known);
    }

    public function test_three_lockout_cycles_force_reset_and_block_existing_credentials(): void
    {
        $user = $this->customer();
        $existingToken = $user->createToken('existing-device')->plainTextToken;
        config(['session.driver' => 'database']);
        \DB::table('sessions')->insert([
            'id' => 'security-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'serialized', 'last_activity' => now()->timestamp,
        ]);

        foreach ([60, 120, 300] as $duration) {
            $this->apiLogin($user, 'wrong-one')->assertUnauthorized();
            $this->apiLogin($user, 'wrong-two')->assertUnauthorized();
            $this->apiLogin($user, 'wrong-three')->assertStatus(429)
                ->assertJsonPath('data.security.status', 'locked')
                ->assertJsonPath('data.security.lockout_duration_seconds', $duration);
            $this->travel($duration)->seconds();
        }
        $this->apiLogin($user, 'wrong-after-final-lockout')
            ->assertStatus(429)
            ->assertJsonPath('data.security.status', 'password_reset_required')
            ->assertJsonPath('data.security.password_reset_required', true);

        $user->refresh();
        $this->assertTrue($user->password_reset_required);
        $this->assertCount(1, $user->tokens);
        $this->assertDatabaseHas('sessions', ['id' => 'security-session']);

        $googleResult = app(AccountLoginSecurity::class)->issueGoogleToken($user, 'google-device');
        $this->assertSame('password_reset_required', $googleResult['status']);
        $this->assertArrayNotHasKey('token', $googleResult);

        $this->withToken($existingToken)
            ->getJson('/api/v1/profile')
            ->assertStatus(429)
            ->assertJsonPath('data.security.password_reset_required', true);
    }

    public function test_failed_google_authentication_does_not_count_as_password_failure(): void
    {
        $user = $this->customer();
        config(['services.firebase.credentials' => null]);

        $this->postJson('/api/v1/auth/google', ['id_token' => 'invalid-test-token'])
            ->assertStatus(503);

        $this->assertSame(0, $user->fresh()->failed_login_attempts);
        $this->assertSame(0, $user->fresh()->login_lockout_cycles);
    }

    public function test_reset_code_has_fixed_five_minute_expiry_and_reset_clears_security_state(): void
    {
        $user = $this->customer();
        $user->forceFill([
            'failed_login_attempts' => 2,
            'login_lockout_cycles' => 3,
            'login_locked_until' => now()->addMinutes(5),
            'password_reset_required' => true,
        ])->save();
        $user->createToken('existing-device');

        config(['session.driver' => 'database']);
        \DB::table('sessions')->insert([
            'id' => 'reset-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => 'serialized', 'last_activity' => now()->timestamp,
        ]);

        $cacheKey = 'web-password-reset-code:'.hash('sha256', strtolower($user->email));
        Cache::put($cacheKey, [
            'hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], now()->addMinutes(5));

        $this->travel(4)->minutes();
        $this->from('/forgot-password')->post('/forgot-password/verify-code', [
            'email' => $user->email, 'code' => '000000', 'password' => 'New-password-456',
            'password_confirmation' => 'New-password-456',
        ])->assertSessionHasErrors('code');

        // A wrong code does not extend the original five-minute deadline.
        $this->travel(1)->minutes();
        $this->from('/forgot-password')->post('/forgot-password/verify-code', [
            'email' => $user->email, 'code' => '123456', 'password' => 'New-password-456',
            'password_confirmation' => 'New-password-456',
        ])->assertSessionHasErrors('code');

        $this->assertFalse(Hash::check('New-password-456', $user->fresh()->password));
        $this->assertSame(5, config('auth.passwords.users.expire'));

        Cache::put($cacheKey, [
            'hash' => Hash::make('654321'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5)->timestamp,
        ], now()->addMinutes(5));
        $this->actingAs($user, 'web')->post('/forgot-password/verify-code', [
            'email' => $user->email, 'code' => '654321', 'password' => 'New-password-456',
            'password_confirmation' => 'New-password-456',
        ])->assertRedirect(route('password.reset.done'));
        $this->assertGuest('web');

        $user->refresh();
        $this->assertTrue(Hash::check('New-password-456', $user->password));
        $this->assertSame(0, $user->failed_login_attempts);
        $this->assertSame(0, $user->login_lockout_cycles);
        $this->assertNull($user->login_locked_until);
        $this->assertFalse($user->password_reset_required);
        $this->assertCount(0, $user->tokens);
        $this->assertDatabaseMissing('sessions', ['id' => 'reset-session']);
    }

    public function test_password_broker_token_expires_after_five_minutes(): void
    {
        $user = $this->customer();
        $token = Password::broker()->createToken($user);

        $this->assertTrue(Password::broker()->tokenExists($user, $token));
        $this->travel(5)->minutes();
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
    }

    public function test_forced_password_reset_blocks_existing_web_session(): void
    {
        $suffix = Str::lower(Str::random(10));
        $admin = User::create([
            'name' => 'Security Admin',
            'username' => 'admin'.$suffix,
            'email' => $suffix.'@example.test',
            'password' => 'Correct-password-123',
            'role' => 'admin',
            'status' => 'active',
        ]);
        $admin->forceFill(['password_reset_required' => true])->save();

        $this->actingAs($admin)->get('/admin')->assertRedirect('/forgot-password');
        $this->get('/forgot-password')->assertOk();
    }

    public function test_forced_password_reset_still_allows_explicit_api_logout(): void
    {
        $user = $this->customer();
        $user->forceFill(['password_reset_required' => true])->save();
        $newToken = $user->createToken('forced-reset-device');

        $this->withToken($newToken->plainTextToken)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $newToken->accessToken->id,
        ]);
    }
}
