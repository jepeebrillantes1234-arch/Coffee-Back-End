<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\StaffShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminNotificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['logging.default' => 'stderr', 'view.compiled' => sys_get_temp_dir()]);
        app()->forgetInstance('blade.compiler');
        app()->forgetInstance('view.engine.resolver');
    }

    public function test_customer_support_message_creates_admin_history_record_and_opening_it_marks_read_without_deleting(): void
    {
        $admin = $this->user('admin', 'History Admin');
        $customer = $this->user('customer', 'History Customer');

        $conversation = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/support/conversations')
            ->assertOk()->json('data.id');
        $this->postJson("/api/v1/support/conversations/{$conversation}/messages", ['body' => 'Please help with my order.'])
            ->assertCreated();

        $notification = DB::table('app_notifications')->where('user_id', $admin->id)->where('type', 'customer_support')->first();
        $this->assertNotNull($notification);
        $this->assertFalse((bool) $notification->is_read);
        $this->actingAs($admin)->getJson(route('admin.support.notifications'))->assertOk()->assertJsonPath('unread_count', 1);
        $this->getJson(route('admin.support.notifications'))->assertOk()->assertJsonPath('unread_count', 1);
        $this->assertDatabaseCount('app_notifications', 1);

        $this->actingAs($admin)->get(route('admin.notifications.index'))
            ->assertOk()->assertSee('Notification History')->assertSee('History Customer')->assertSee('Unread')
            ->assertSee('href="'.route('admin.notifications.index').'"', false);

        $this->get(route('admin.notifications.show', $notification->id))
            ->assertOk()->assertSee('Customer support message')->assertSee('Open related record');

        $this->assertDatabaseHas('app_notifications', ['id' => $notification->id, 'user_id' => $admin->id, 'is_read' => true]);
        $this->assertDatabaseCount('app_notifications', 1);
        $this->getJson(route('admin.support.notifications'))->assertOk()->assertJsonPath('unread_count', 0);
    }

    public function test_admin_notification_history_is_scoped_to_the_logged_in_admin(): void
    {
        $owner = $this->user('admin', 'Owner Admin');
        $other = $this->user('admin', 'Other Admin');
        $id = DB::table('app_notifications')->insertGetId([
            'user_id' => $owner->id,
            'title' => 'Private notice',
            'message' => 'Owner only',
            'type' => 'general',
            'data' => json_encode([]),
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($other)->get(route('admin.notifications.index'))->assertOk()->assertDontSee('Private notice');
        $this->get(route('admin.notifications.show', $id))->assertNotFound();
        $this->assertDatabaseHas('app_notifications', ['id' => $id, 'is_read' => false]);
    }

    public function test_admin_activity_log_filters_support_user_role_action_and_date(): void
    {
        $admin = $this->user('admin', 'Log Filter Admin');
        $staff = $this->user('staff', 'Log Filter Staff');
        $rider = $this->user('rider', 'Log Filter Rider');

        DB::table('activity_logs')->insert([
            ['user_id' => $staff->id, 'action' => 'prepare', 'module' => 'orders', 'description' => 'Order A prepared', 'ip_address' => null, 'created_at' => now()->startOfDay()->addHour()],
            ['user_id' => $rider->id, 'action' => 'pickup', 'module' => 'deliveries', 'description' => 'Order B picked up', 'ip_address' => null, 'created_at' => now()],
        ]);

        $this->actingAs($admin)->get('/admin/activity-logs?role=rider&action=pickup&from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()->assertSee('Log Filter Rider')->assertSee('Order B picked up')->assertDontSee('Order A prepared');
    }

    public function test_successful_staff_order_status_change_is_audited_with_reference_and_transition(): void
    {
        $staff = $this->user('staff', 'Order Processing Staff');
        $customer = $this->user('customer', 'Order Test Customer');
        StaffShift::create(['user_id' => $staff->id, 'status' => 'open', 'opening_fund' => 500, 'opened_at' => now()]);
        $order = Order::create([
            'user_id' => $customer->id,
            'order_number' => 'KJM-AUDIT-1001',
            'order_type' => 'pickup',
            'status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($staff)->post(route('staff.orders.advance', $order), ['action' => 'confirm'])->assertRedirect();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staff->id,
            'action' => 'confirm',
            'module' => 'orders',
            'description' => "KJM-AUDIT-1001 (#{$order->id}): pending -> confirmed",
        ]);
    }

    private function user(string $role, string $name): User
    {
        $suffix = Str::lower(Str::random(10));
        return User::create([
            'name' => $name,
            'username' => $role.$suffix,
            'email' => $suffix.'@example.test',
            'password' => 'SecureTestPassword-77',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
