<?php

namespace Tests\Feature;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RiderCustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_rider_can_start_send_and_read_own_support_conversation(): void
    {
        $rider = $this->user('rider');

        $this->actingAs($rider, 'sanctum')
            ->getJson('/api/v1/support/conversation')
            ->assertOk()
            ->assertJsonPath('data', null);

        $started = $this->actingAs($rider, 'sanctum')
            ->postJson('/api/v1/support/conversations')
            ->assertOk()
            ->assertJsonPath('success', true);

        $conversationId = (int) $started->json('data.id');

        $this->postJson("/api/v1/support/conversations/{$conversationId}/messages", [
            'body' => 'I need help with a delivery assignment.',
        ])->assertCreated()
            ->assertJsonPath('data.id', $conversationId)
            ->assertJsonFragment(['body' => 'I need help with a delivery assignment.']);

        $this->assertDatabaseHas('support_conversations', [
            'id' => $conversationId,
            'user_id' => $rider->id,
        ]);
        $this->assertDatabaseHas('support_messages', [
            'conversation_id' => $conversationId,
            'sender_type' => 'customer',
            'sender_id' => $rider->id,
            'body' => 'I need help with a delivery assignment.',
        ]);

        $this->getJson('/api/v1/support/conversation')
            ->assertOk()
            ->assertJsonPath('data.id', $conversationId);
        $this->getJson("/api/v1/support/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonFragment(['body' => 'I need help with a delivery assignment.']);
    }

    public function test_rider_cannot_read_another_users_support_conversation(): void
    {
        $rider = $this->user('rider');
        $otherCustomer = $this->user('customer');
        $conversation = SupportConversation::create([
            'user_id' => $otherCustomer->id,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $this->actingAs($rider, 'sanctum')
            ->getJson("/api/v1/support/conversations/{$conversation->id}")
            ->assertNotFound();

        $this->postJson("/api/v1/support/conversations/{$conversation->id}/messages", [
            'body' => 'Attempt to access another users conversation.',
        ])->assertNotFound();
    }

    public function test_admin_and_staff_do_not_gain_access_to_customer_support_api(): void
    {
        $customer = $this->user('customer');
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/support/conversation')
            ->assertOk()
            ->assertJsonPath('data', null);

        foreach (['admin', 'staff'] as $role) {
            $this->actingAs($this->user($role), 'sanctum')
                ->getJson('/api/v1/support/conversation')
                ->assertForbidden();
        }
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
