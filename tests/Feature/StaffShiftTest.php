<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StaffShift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffShiftTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $email = 'staff@example.test'): User
    {
        return User::create(['name' => 'Test Staff', 'email' => $email, 'password' => 'password', 'role' => 'staff', 'status' => 'active']);
    }

    public function test_staff_must_open_shift_before_dashboard_and_cannot_open_duplicate(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff)->get(route('staff.orders'))->assertRedirect(route('staff.shift'));
        $this->actingAs($staff)->post(route('staff.shift.open'), ['opening_fund' => 500])->assertRedirect(route('staff.orders'));
        $this->assertDatabaseCount('staff_shifts', 1);
        $this->actingAs($staff)->post(route('staff.shift.open'), ['opening_fund' => 900])->assertSessionHasErrors('opening_fund');
        $this->assertDatabaseCount('staff_shifts', 1);
        $this->actingAs($staff)->get(route('staff.orders'))->assertOk();
    }

    public function test_cash_pos_sale_is_linked_to_open_shift_and_close_reconciles_separate_online_payments(): void
    {
        $staff = $this->staff();
        $this->actingAs($staff)->post(route('staff.shift.open'), ['opening_fund' => 500])->assertRedirect(route('staff.orders'));
        $shift = StaffShift::firstOrFail();
        $category = Category::create(['name' => 'Coffee']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Latte', 'base_price' => 100, 'is_available' => true]);

        $this->actingAs($staff)->postJson(route('staff.pos.store'), ['items' => [['product_id' => $product->id, 'quantity' => 2]], 'cash' => 250])->assertCreated();
        $sale = Order::where('staff_shift_id', $shift->id)->firstOrFail();
        $this->assertSame('cash', $sale->payment_method);
        $this->assertEquals(200, $sale->total);

        $onlineOrder = Order::create(['order_number' => 'ONLINE-1', 'user_id' => $this->customer()->id, 'order_type' => 'pickup', 'status' => 'confirmed', 'subtotal' => 75, 'total' => 75, 'payment_method' => 'qrph', 'payment_status' => 'paid']);
        Payment::create(['order_id' => $onlineOrder->id, 'method' => 'qrph', 'amount' => 75, 'status' => 'paid', 'paid_at' => now()]);

        $this->actingAs($staff)->post(route('staff.shift.close', $shift), ['actual_cash' => 700])->assertRedirect(route('staff.shift.report', $shift));
        $shift->refresh();
        $this->assertEquals(200, $shift->cash_sales);
        $this->assertEquals(75, $shift->online_payments);
        $this->assertEquals(700, $shift->expected_cash);
        $this->assertEquals(700, $shift->actual_cash);
        $this->assertSame('balanced', $shift->reconciliation_status);
        $this->actingAs($staff)->get(route('staff.pos'))->assertRedirect(route('staff.shift'));
        $this->actingAs($staff)->postJson(route('staff.pos.store'), ['items' => [], 'cash' => 0])->assertRedirect(route('staff.shift'));
        $this->assertDatabaseCount('orders', 2);
        $this->actingAs($staff)->get(route('staff.shift.report', $shift))->assertOk()->assertSee('Print Report');
    }

    public function test_cash_refunds_and_deductions_are_subtracted_from_expected_drawer_cash(): void
    {
        $staff = $this->staff();
        $shift = StaffShift::create(['user_id' => $staff->id, 'status' => 'open', 'opening_fund' => 500, 'opened_at' => now()]);
        $order = Order::create(['order_number' => 'CASH-REFUND', 'staff_id' => $staff->id, 'staff_shift_id' => $shift->id, 'order_type' => 'walkin', 'status' => 'preparing', 'subtotal' => 100, 'total' => 100, 'payment_method' => 'cash', 'payment_status' => 'refunded']);
        Payment::create(['order_id' => $order->id, 'method' => 'cash', 'amount' => 100, 'status' => 'refunded']);

        $this->actingAs($staff)->post(route('staff.shift.close', $shift), ['actual_cash' => 390, 'cash_deductions' => 10, 'deduction_reason' => 'Till adjustment'])->assertRedirect(route('staff.shift.report', $shift));
        $this->assertEquals(100, $shift->fresh()->cash_refunds);
        $this->assertEquals(390, $shift->fresh()->expected_cash);
        $this->assertSame('balanced', $shift->fresh()->reconciliation_status);
    }

    public function test_admin_can_search_and_print_shift_report_but_staff_cannot_view_another_staff_report(): void
    {
        $staff = $this->staff();
        $other = $this->staff('other@example.test');
        $shift = StaffShift::create(['user_id' => $other->id, 'status' => 'closed', 'opening_fund' => 300, 'opened_at' => now()->subHour(), 'closed_at' => now(), 'cash_sales' => 50, 'online_payments' => 0, 'cash_refunds' => 0, 'cash_deductions' => 0, 'expected_cash' => 350, 'actual_cash' => 340, 'cash_difference' => -10, 'reconciliation_status' => 'shortage', 'closed_by' => $other->id]);

        $this->actingAs($staff)->get(route('admin.shifts.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('staff.shift.report', $shift))->assertNotFound();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.shifts.index', ['search' => 'Test Staff']))->assertOk()->assertSee('Shortages');
        $this->actingAs($admin)->get(route('admin.shifts.report', $shift))->assertOk()->assertSee('Print Report')->assertSee('₱340.00');
    }

    private function customer(): User
    {
        return User::create(['name' => 'Test Customer', 'email' => 'customer@example.test', 'password' => 'password', 'role' => 'customer', 'status' => 'active']);
    }
}
