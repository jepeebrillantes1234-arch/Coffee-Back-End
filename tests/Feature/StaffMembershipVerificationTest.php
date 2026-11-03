<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\StaffShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffMembershipVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_verify_a_paid_active_membership_by_card_number(): void
    {
        $staff = $this->staff();
        $this->openShift($staff);
        $member = $this->customerWithMembership();
        $member->payments()->create([
            'provider' => 'simulated',
            'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($staff)->postJson(route('staff.pos.membership.verify'), [
            'card_number' => strtolower($member->member_id),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.card_number', $member->member_id)
            ->assertJsonPath('data.member_name', $member->user->name)
            ->assertJsonPath('data.status', 'eligible')
            ->assertJsonPath('data.discount_enabled', true)
            ->assertJsonPath('data.discount_percent', 10);
    }

    public function test_unknown_unpaid_inactive_and_expired_memberships_are_rejected(): void
    {
        $staff = $this->staff();
        $this->openShift($staff);

        $this->actingAs($staff)->postJson(route('staff.pos.membership.verify'), ['card_number' => 'KJM-UNKNOWN'])
            ->assertUnprocessable();

        $unpaid = $this->customerWithMembership();
        $this->actingAs($staff)->postJson(route('staff.pos.membership.verify'), ['card_number' => $unpaid->member_id])
            ->assertUnprocessable();

        $inactive = $this->customerWithMembership('inactive');
        $inactive->payments()->create([
            'provider' => 'simulated',
            'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $this->actingAs($staff)->postJson(route('staff.pos.membership.verify'), ['card_number' => $inactive->member_id])
            ->assertUnprocessable();

        $expired = $this->customerWithMembership('active', now()->subDay());
        $expired->payments()->create([
            'provider' => 'simulated',
            'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $this->actingAs($staff)->postJson(route('staff.pos.membership.verify'), ['card_number' => $expired->member_id])
            ->assertUnprocessable();
    }

    public function test_non_staff_cannot_verify_a_membership(): void
    {
        $customer = $this->user('customer');
        $this->actingAs($customer)->postJson(route('staff.pos.membership.verify'), ['card_number' => 'KJM-ANY'])
            ->assertForbidden();
    }

    private function staff(): User
    {
        return $this->user('staff');
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Test',
            'email' => Str::lower(Str::random(12)) . '@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function openShift(User $staff): void
    {
        StaffShift::create(['user_id' => $staff->id, 'status' => 'open', 'opening_fund' => 500, 'opened_at' => now()]);
    }

    private function customerWithMembership(string $status = 'active', $expiresAt = null): Membership
    {
        $user = $this->user('customer');

        return Membership::create([
            'user_id' => $user->id,
            'member_id' => 'KJM-' . Str::upper(Str::random(10)),
            'status' => $status,
            'points_balance' => 0,
            'activated_at' => $status === 'active' ? now() : null,
            'expires_at' => $expiresAt,
        ]);
    }
}
