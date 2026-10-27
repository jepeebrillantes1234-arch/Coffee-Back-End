<?php

namespace Tests\Feature;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep compiled views in the writable system temp directory for tests.
        config(['view.compiled' => sys_get_temp_dir()]);
    }

    public function test_staff_can_send_messages_and_admin_can_reply_and_update_ticket_status(): void
    {
        $staff = $this->user('staff');
        $admin = $this->user('admin');

        $this->actingAs($staff)
            ->from('/staff/support')
            ->post('/staff/support', ['body' => 'The order queue will not load on my tablet.'])
            ->assertRedirect();

        $conversation = SupportConversation::where('user_id', $staff->id)->firstOrFail();
        $this->assertSame('waiting_admin', $conversation->status);
        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => $conversation->id,
            'sender_type' => 'staff',
            'sender_id' => $staff->id,
            'body' => 'The order queue will not load on my tablet.',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->id,
            'type' => 'staff_support',
        ]);

        $this->actingAs($admin)
            ->get('/admin/support/' . $conversation->id)
            ->assertOk()
            ->assertSee('Staff profile')
            ->assertSee('The order queue will not load on my tablet.');

        $this->post('/admin/support/' . $conversation->id . '/reply', [
            'body' => 'Thanks. We are checking the tablet layout now.',
        ])->assertRedirect();
        $this->assertSame('open', $conversation->fresh()->status);

        $this->post('/admin/support/' . $conversation->id . '/status', ['status' => 'closed'])
            ->assertRedirect();
        $this->assertSame('closed', $conversation->fresh()->status);

        $this->actingAs($staff)
            ->get('/staff/support?conversation=' . $conversation->id)
            ->assertOk()
            ->assertSee('Thanks. We are checking the tablet layout now.')
            ->assertSee('This ticket is closed.');
    }

    public function test_staff_cannot_read_another_staff_ticket_and_customers_cannot_open_staff_support(): void
    {
        $staff = $this->user('staff');
        $other = $this->user('staff');
        $customer = $this->user('customer');
        $conversation = SupportConversation::create([
            'user_id' => $other->id,
            'status' => 'waiting_admin',
            'last_message_at' => now(),
        ]);
        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'staff',
            'sender_id' => $other->id,
            'body' => 'Private staff ticket',
        ]);

        $this->actingAs($staff)
            ->get('/staff/support/' . $conversation->id . '/poll')
            ->assertNotFound();
        $this->actingAs($customer)
            ->get('/staff/support')
            ->assertForbidden();
    }

    private function user(string $role): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'name' => ucfirst($role) . ' Support Test',
            'username' => $role . $suffix,
            'email' => $suffix . '@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
