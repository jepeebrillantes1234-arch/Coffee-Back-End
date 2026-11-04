<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipCardPrint;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminMembershipCardPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_paid_membership_shows_personalized_card_and_same_member_id_qr(): void
    {
        $admin = $this->user('admin');
        $membership = $this->membership($this->user('customer'), 'active');
        $this->paidPayment($membership);

        $this->actingAs($admin)
            ->get(route('admin.memberships.show', $membership))
            ->assertOk()
            ->assertSee('Membership Card')
            ->assertSee('Ready to Print')
            ->assertSee('data-membership-qr="' . $membership->member_id . '"', false)
            ->assertSee('images/membership-cards/card.png')
            ->assertSee('images/membership-cards/card1.png')
            ->assertSee('85.60mm 53.98mm')
            ->assertSee('Membership Payment Transactions')
            ->assertSee('Points History')
            ->assertSee('Reward Redemption History');
    }

    public function test_pending_or_unpaid_membership_is_not_ready_to_print(): void
    {
        $admin = $this->user('admin');
        $membership = $this->membership($this->user('customer'), 'pending');
        $membership->payments()->create([
            'provider' => 'paymongo',
            'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.memberships.show', $membership))
            ->assertOk()
            ->assertSee('Not Ready to Print')
            ->assertDontSee('Mark as Printed')
            ->assertDontSee('Print Card');
    }

    public function test_only_admin_can_view_and_record_membership_card_prints(): void
    {
        $membership = $this->membership($this->user('customer'), 'active');
        $this->paidPayment($membership);

        $this->actingAs($this->user('staff'))
            ->get(route('admin.memberships.show', $membership))
            ->assertForbidden();

        $this->post(route('admin.memberships.card.printed', $membership))->assertForbidden();
        $this->assertDatabaseCount('membership_card_prints', 0);
    }

    public function test_mark_printed_and_reprint_create_auditable_history_without_duplicate_membership_or_qr(): void
    {
        $admin = $this->user('admin');
        $membership = $this->membership($this->user('customer'), 'active');
        $this->paidPayment($membership);

        $this->actingAs($admin)
            ->post(route('admin.memberships.card.printed', $membership))
            ->assertRedirect();
        $this->assertDatabaseHas('membership_card_prints', [
            'membership_id' => $membership->id,
            'admin_id' => $admin->id,
            'print_number' => 1,
        ]);
        $this->actingAs($admin)
            ->post(route('admin.memberships.card.printed', $membership))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseCount('membership_card_prints', 1);

        $this->actingAs($admin)
            ->post(route('admin.memberships.card.reprint', $membership))
            ->assertRedirect();
        $this->assertDatabaseHas('membership_card_prints', [
            'membership_id' => $membership->id,
            'admin_id' => $admin->id,
            'print_number' => 2,
        ]);
        $this->assertSame(1, Membership::where('user_id', $membership->user_id)->count());
        $this->assertSame(2, MembershipCardPrint::where('membership_id', $membership->id)->count());
        $this->assertSame(1, Membership::where('member_id', $membership->member_id)->count());
    }

    public function test_repeated_verified_payment_callback_keeps_one_membership_and_card_identity(): void
    {
        $customer = $this->user('customer');
        $membership = $this->membership($customer, 'pending');
        $payment = $membership->payments()->create([
            'provider' => 'paymongo',
            'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'pending',
        ]);

        app(LoyaltyService::class)->markPaid($payment, 'pay_card_test', 10000);
        app(LoyaltyService::class)->markPaid($payment->fresh(), 'pay_card_test', 10000);

        $this->assertSame(1, Membership::where('user_id', $customer->id)->count());
        $this->assertSame(1, MembershipPayment::where('membership_id', $membership->id)->where('status', 'paid')->count());
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame(1, Membership::where('member_id', $membership->member_id)->count());
    }

    public function test_expired_membership_is_not_ready_to_print(): void
    {
        $admin = $this->user('admin');
        $membership = $this->membership($this->user('customer'), 'active');
        $membership->update(['expires_at' => now()->subDay()]);
        $this->paidPayment($membership);

        $this->actingAs($admin)
            ->get(route('admin.memberships.show', $membership))
            ->assertOk()
            ->assertSee('Expired — Not Ready to Print')
            ->assertDontSee('Print Card');
    }

    public function test_active_admin_uploaded_artwork_matches_customer_app_configuration(): void
    {
        $admin = $this->user('admin');
        $membership = $this->membership($this->user('customer'), 'active');
        $this->paidPayment($membership);
        Settings::set('membership_card_design_active', '1');
        Settings::set('membership_card_front_design', 'membership-cards/uploaded-front.png');
        Settings::set('membership_card_back_design', 'membership-cards/uploaded-back.png');

        $this->actingAs($admin)
            ->get(route('admin.memberships.show', $membership))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('membership-cards/uploaded-front.png'))
            ->assertSee(Storage::disk('public')->url('membership-cards/uploaded-back.png'));
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Card Tester',
            'username' => $role . Str::lower(Str::random(10)),
            'email' => Str::lower(Str::random(12)) . '@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function membership(User $customer, string $status): Membership
    {
        return Membership::create([
            'user_id' => $customer->id,
            'member_id' => 'KJM-' . Str::upper(Str::random(10)),
            'status' => $status,
            'points_balance' => 8,
        ]);
    }

    private function paidPayment(Membership $membership): MembershipPayment
    {
        return $membership->payments()->create([
            'provider' => 'paymongo',
            'payment_reference' => 'KJMM-' . Str::upper(Str::random(16)),
            'amount' => 100,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}