<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCancellationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancellation_history_is_admin_only_and_uses_saved_order_records(): void
    {
        $customer = $this->user('customer');
        $order = Order::create([
            'order_number' => 'KJM-CANCEL-HISTORY',
            'user_id' => $customer->id,
            'order_type' => 'delivery',
            'status' => 'cancelled',
            'subtotal' => 200,
            'total' => 230,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'reject_reason' => 'Change Address',
        ]);
        \DB::table('order_status_logs')->insert([
            'order_id' => $order->id,
            'status' => 'cancelled',
            'changed_by' => $customer->id,
            'created_at' => now(),
        ]);

        $this->get('/admin/orders/cancellations')->assertRedirect('/login');
        $this->actingAs($customer)->get('/admin/orders/cancellations')->assertForbidden();

        $this->actingAs($this->user('admin'))
            ->get('/admin/orders/cancellations?reason=Change%20Address')
            ->assertOk()
            ->assertSee('KJM-CANCEL-HISTORY')
            ->assertSee('Cancellation Customer')
            ->assertSee('Change Address')
            ->assertSee('COD')
            ->assertSee('230.00');
    }

    private function user(string $role): User
    {
        $suffix = Str::lower(Str::random(10));

        return User::create([
            'name' => $role === 'admin' ? 'Admin User' : 'Cancellation Customer',
            'username' => $role.$suffix,
            'email' => $role.$suffix.'@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
