<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FirebaseIdentityVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class FacebookFirebaseLoginTest extends TestCase
{
    use RefreshDatabase;

    private FirebaseIdentityVerifier $verifier;
    private string $credentialsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $this->credentialsPath = tempnam(sys_get_temp_dir(), 'kjm-firebase-test-');
        file_put_contents($this->credentialsPath, 'test-only-placeholder');
        config(['services.firebase.credentials' => $this->credentialsPath]);
        config(['services.firebase.project_id' => 'kjm-coffee-shop']);

        $this->verifier = Mockery::mock(FirebaseIdentityVerifier::class);
        $this->app->instance(FirebaseIdentityVerifier::class, $this->verifier);
    }

    protected function tearDown(): void
    {
        if (isset($this->credentialsPath) && is_file($this->credentialsPath)) {
            unlink($this->credentialsPath);
        }

        parent::tearDown();
    }

    public function test_linked_facebook_account_can_sign_in_when_email_is_unverified(): void
    {
        $user = $this->customer('linked-facebook@example.test', 'firebase-user-1');
        $this->mockIdentity([
            'uid' => 'firebase-user-1',
            'email' => $user->email,
            'email_verified' => false,
            'name' => $user->name,
            'sign_in_provider' => 'facebook.com',
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-facebook-token',
            'provider' => 'facebook',
            'device_name' => 'kjm-facebook-app',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.role', 'customer');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame('firebase-user-1', $user->fresh()->firebase_uid);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unlinked_facebook_account_with_verified_email_is_created_for_verified_uid(): void
    {
        $this->mockIdentity([
            'uid' => 'verified-facebook-user',
            'email' => 'verified-facebook@example.test',
            'email_verified' => true,
            'name' => 'Verified Facebook User',
            'sign_in_provider' => 'facebook.com',
        ]);

        $response = $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-facebook-token',
            'provider' => 'facebook',
        ])->assertOk()->assertJsonPath('data.user.role', 'customer');

        $this->assertDatabaseHas('users', [
            'email' => 'verified-facebook@example.test',
            'firebase_uid' => 'verified-facebook-user',
            'role' => 'customer',
        ]);
        $this->assertNotEmpty($response->json('data.token'));
    }
    public function test_unlinked_facebook_account_with_unverified_email_is_not_matched_or_created(): void
    {
        $existing = $this->customer('existing@example.test', null);
        $this->mockIdentity([
            'uid' => 'new-facebook-user',
            'email' => $existing->email,
            'email_verified' => false,
            'name' => 'Facebook User',
            'sign_in_provider' => 'facebook.com',
        ]);

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-facebook-token',
            'provider' => 'facebook',
        ])->assertUnauthorized()->assertJsonPath('data.auth_action', 'facebook_email_verification_required');

        $this->assertSame(1, User::count());
        $this->assertNull($existing->fresh()->firebase_uid);
    }

    public function test_unlinked_facebook_account_without_email_is_rejected(): void
    {
        $this->mockIdentity([
            'uid' => 'new-facebook-user',
            'email' => '',
            'email_verified' => false,
            'name' => 'Facebook User',
            'sign_in_provider' => 'facebook.com',
        ]);

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-facebook-token',
            'provider' => 'facebook',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_facebook_request_rejects_a_different_verified_provider_claim(): void
    {
        $user = $this->customer('linked-google@example.test', 'google-user-1');
        $this->mockIdentity([
            'uid' => 'google-user-1',
            'email' => $user->email,
            'email_verified' => true,
            'name' => $user->name,
            'sign_in_provider' => 'google.com',
        ], 'verified-google-token');

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-google-token',
            'provider' => 'facebook',
        ])->assertUnauthorized();

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_verified_facebook_email_cannot_claim_an_existing_unlinked_laravel_account(): void
    {
        $existing = $this->customer('existing@example.test', null);
        $this->mockIdentity([
            'uid' => 'new-facebook-user',
            'email' => $existing->email,
            'email_verified' => true,
            'name' => 'Facebook User',
            'sign_in_provider' => 'facebook.com',
        ]);

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-facebook-token',
            'provider' => 'facebook',
        ])->assertStatus(409);

        $this->assertSame(1, User::count());
        $this->assertNull($existing->fresh()->firebase_uid);
        $this->assertCount(0, $existing->fresh()->tokens);
    }
    public function test_mismatched_firebase_uid_cannot_access_another_customers_account(): void
    {
        $target = $this->customer('target@example.test', 'target-firebase-uid');
        $this->mockIdentity([
            'uid' => 'different-firebase-uid',
            'email' => $target->email,
            'email_verified' => true,
            'name' => 'Different Identity',
            'sign_in_provider' => 'facebook.com',
        ]);

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-facebook-token',
            'provider' => 'facebook',
        ])->assertStatus(409);

        $this->assertCount(0, $target->fresh()->tokens);
        $this->assertSame('target-firebase-uid', $target->fresh()->firebase_uid);
    }

    public function test_google_firebase_login_and_password_login_remain_supported(): void
    {
        $user = $this->customer('google@example.test', null);
        $this->mockIdentity([
            'uid' => 'google-firebase-uid',
            'email' => $user->email,
            'email_verified' => true,
            'name' => $user->name,
            'sign_in_provider' => 'google.com',
        ], 'verified-google-token');

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-google-token',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'Correct-password-123',
            'device_name' => 'password-device',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_facebook_verification_failure_logs_only_safe_diagnostic_fields(): void
    {
        Log::spy();
        $this->verifier->shouldReceive('verify')
            ->once()
            ->andThrow(new \RuntimeException('must not be logged'));

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-facebook-token',
            'provider' => 'facebook',
        ])->assertUnauthorized();

        Log::shouldHaveReceived('warning')
            ->with('Facebook authentication diagnostic', Mockery::on(
                fn (array $context): bool => $context === [
                    'reason' => 'firebase_token_verification_failed',
                    'provider_claim' => 'missing',
                    'email_present' => false,
                    'email_verified' => false,
                    'exception_class' => \RuntimeException::class,
                ],
            ))
            ->once();
    }

    public function test_facebook_provider_mismatch_logs_whitelisted_claim_category(): void
    {
        Log::spy();
        $this->mockIdentity([
            'uid' => 'google-user-1',
            'email' => 'linked@example.test',
            'email_verified' => true,
            'name' => 'Linked User',
            'sign_in_provider' => 'google.com',
        ], 'verified-google-token');

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-google-token',
            'provider' => 'facebook',
        ])->assertUnauthorized();

        Log::shouldHaveReceived('warning')
            ->with('Facebook authentication diagnostic', Mockery::on(
                fn (array $context): bool => $context === [
                    'reason' => 'provider_claim_mismatch',
                    'provider_claim' => 'other',
                    'email_present' => true,
                    'email_verified' => true,
                    'uid_linked' => false,
                ],
            ))
            ->once();
    }

    public function test_unlinked_facebook_email_rejection_logs_presence_and_verification_only(): void
    {
        Log::spy();
        $this->mockIdentity([
            'uid' => 'new-facebook-user',
            'email' => 'private@example.test',
            'email_verified' => false,
            'name' => 'Facebook User',
            'sign_in_provider' => 'facebook.com',
        ]);

        $this->postJson('/api/v1/auth/google', [
            'id_token' => 'verified-facebook-token',
            'provider' => 'facebook',
        ])->assertUnauthorized();

        Log::shouldHaveReceived('warning')
            ->with('Facebook authentication diagnostic', Mockery::on(
                fn (array $context): bool => $context === [
                    'reason' => 'facebook_email_unverified_or_missing',
                    'provider_claim' => 'facebook.com',
                    'email_present' => true,
                    'email_verified' => false,
                    'uid_linked' => false,
                ],
            ))
            ->once();
    }

    private function mockIdentity(array $identity, string $token = 'verified-facebook-token'): void
    {
        $this->verifier->shouldReceive('verify')
            ->once()
            ->with($token, $this->credentialsPath, 'kjm-coffee-shop')
            ->andReturn($identity);
    }

    private function customer(string $email, ?string $firebaseUid): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'name' => 'Facebook Test Customer',
            'username' => 'facebook'.$suffix,
            'email' => $email,
            'password' => 'Correct-password-123',
            'role' => 'customer',
            'status' => 'active',
            'firebase_uid' => $firebaseUid,
        ]);
    }
}
