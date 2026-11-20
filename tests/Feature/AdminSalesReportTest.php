<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminSalesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_totals_count_only_paid_completed_orders_and_successful_refunds(): void
    {
        $admin = $this->user('admin');
        $customer = $this->user('customer');
        $eligible = $this->order($customer, 'delivered', 'paid', 100, now());
        $this->item($eligible, 'Beef Nachos', 2, 50);
        $this->refund($eligible, 'succeeded', 2500);
        $this->refund($eligible, 'pending', 900);

        $this->order($customer, 'completed', 'unpaid', 500, now());
        $this->order($customer, 'cancelled', 'paid', 700, now());
        $this->order($customer, 'preparing', 'paid', 900, now());

        $this->actingAs($admin)
            ->get('/admin/reports/sales?range=today')
            ->assertOk()
            ->assertSee('id="sales-trend-svg"', false)
            ->assertSee('id="top-products-donut"', false)
            ->assertSee('Download Sales Report (PDF)')
            ->assertSee('export=pdf')
            ->assertDontSee('window.print()')
            ->assertViewHas('sales', function (array $sales): bool {
                $this->assertEquals(100.0, $sales['gross']);
                $this->assertSame(1, $sales['orders']);
                $this->assertSame(2, $sales['quantity']);
                $this->assertEquals(25.0, $sales['refunds']);
                $this->assertEquals(75.0, $sales['net']);
                $this->assertSame('Beef Nachos', $sales['products'][0]['name']);
                return true;
            });
    }

    public function test_sales_report_filters_to_custom_date_range_and_is_admin_only(): void
    {
        $admin = $this->user('admin');
        $customer = $this->user('customer');
        $inside = $this->order($customer, 'completed', 'paid', 35, now());
        $this->item($inside, 'Latte', 1, 35);
        $outside = $this->order($customer, 'completed', 'paid', 75, now()->subDays(40));
        $this->item($outside, 'Old Coffee', 3, 25);

        $this->get('/admin/reports/sales?range=today')->assertRedirect('/login');
        $this->actingAs($this->user('customer'))
            ->get('/admin/reports/sales?range=today')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/reports/sales?range=custom&from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertViewHas('sales', function (array $sales): bool {
                $this->assertEquals(35.0, $sales['gross']);
                $this->assertSame(1, $sales['orders']);
                return true;
            });
    }

    public function test_sales_pdf_download_contains_selected_period_sales_and_is_a_real_pdf(): void
    {
        $admin = $this->user('admin');
        $customer = $this->user('customer');
        for ($index = 0; $index < 36; $index++) {
            $order = $this->order($customer, 'delivered', 'paid', 100, now()->subMinutes($index));
            $this->item($order, $index === 0 ? 'Beef Nachos' : 'Latte', 2, 50);
        }
        $this->refund($order, 'succeeded', 2500);
        \DB::table('payments')->where('order_id', $order->id)->update(['reference_no' => 'PAYMENT-TEST-REF']);
        $this->item($order, 'Cinnamon Roll', 1, 10);
        $order->update(['subtotal' => 110, 'total' => 110]);
        $today = now('Asia/Manila')->toDateString();
        $outsideRange = $this->order($customer, 'completed', 'paid', 999, now()->subYears(2));
        $this->item($outsideRange, 'Outside Selected Range', 1, 999);

        $response = $this->actingAs($admin)->get("/admin/reports/sales?range=custom&from={$today}&to={$today}&export=pdf");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload("kjm-sales-report-{$today}-to-{$today}.pdf");

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(1000, strlen($content));
        $this->assertStringContainsString('/Type /Pages', $content);
        preg_match_all('/\/Type\s*\/Page\b(?!s)/', $content, $pageObjects);
        $this->assertGreaterThan(1, count($pageObjects[0]), 'The report should paginate long transaction lists.');
    }

    public function test_today_yesterday_week_month_and_year_filters_use_business_timezone(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 00:30:00', 'Asia/Manila'));
        $admin = $this->user('admin');
        $customer = $this->user('customer');
        $todayOrder = $this->order($customer, 'completed', 'paid', 10, Carbon::parse('2026-01-01 00:20:00', 'Asia/Manila')->utc());
        $this->item($todayOrder, 'Coffee A', 1, 10);
        $yesterdayOrder = $this->order($customer, 'delivered', 'paid', 20, Carbon::parse('2025-12-31 23:50:00', 'Asia/Manila')->utc());
        $this->item($yesterdayOrder, 'Coffee B', 1, 20);

        foreach ([
            'today' => 10.0,
            'yesterday' => 20.0,
            'this_week' => 30.0,
            'this_month' => 10.0,
            'this_year' => 10.0,
        ] as $range => $expectedGross) {
            $this->actingAs($admin)->get('/admin/reports/sales?range='.$range)
                ->assertOk()->assertViewHas('sales', function (array $sales) use ($expectedGross): bool {
                    $this->assertEquals($expectedGross, $sales['gross']);
                    return true;
                });
        }
    }

    public function test_month_picker_filters_every_transaction_in_the_month_and_year_chart_is_clickable_by_month(): void
    {
        $this->travelTo(Carbon::parse('2026-12-10 12:00:00', 'Asia/Manila'));
        $admin = $this->user('admin');
        $customer = $this->user('customer');

        $this->item($this->order($customer, 'completed', 'paid', 25, Carbon::parse('2026-10-31 23:59:00', 'Asia/Manila')->utc()), 'October Coffee', 1, 25);
        $novemberFirst = $this->order($customer, 'completed', 'paid', 40, Carbon::parse('2026-11-01 00:01:00', 'Asia/Manila')->utc());
        $this->item($novemberFirst, 'November Latte', 2, 20);
        $novemberSecond = $this->order($customer, 'delivered', 'paid', 15, Carbon::parse('2026-11-30 23:59:00', 'Asia/Manila')->utc());
        $this->item($novemberSecond, 'November Espresso', 1, 15);
        $this->item($this->order($customer, 'completed', 'paid', 80, Carbon::parse('2026-12-01 00:01:00', 'Asia/Manila')->utc()), 'December Coffee', 4, 20);

        $this->actingAs($admin)
            ->get('/admin/reports/sales?range=custom&month=2026-11')
            ->assertOk()
            ->assertSee('type="month"', false)
            ->assertSee('type="date"', false)
            ->assertSee('value="2026-11"', false)
            ->assertViewHas('from', '2026-11-01')
            ->assertViewHas('to', '2026-11-30')
            ->assertViewHas('sales', function (array $sales): bool {
                $this->assertSame(2, $sales['orders']);
                $this->assertEquals(55.0, $sales['gross']);
                $this->assertCount(12, $sales['trend']);
                $this->assertSame('Jan 2026', $sales['trend'][0]['label']);
                $this->assertSame(['label' => 'Nov 2026', 'month' => '2026-11', 'value' => 55.0], $sales['trend'][10]);
                $this->assertSame('Dec 2026', $sales['trend'][11]['label']);
                $this->assertCount(2, $sales['transactions']);
                return true;
            });

        $this->actingAs($admin)
            ->get('/admin/reports/sales?range=custom&from=2026-11-15&to=2026-11-30')
            ->assertOk()
            ->assertViewHas('from', '2026-11-15')
            ->assertViewHas('to', '2026-11-30')
            ->assertViewHas('sales', function (array $sales): bool {
                $this->assertSame(1, $sales['orders']);
                $this->assertEquals(15.0, $sales['gross']);
                $this->assertSame(55.0, $sales['trend'][10]['value']);
                return true;
            });

        $annualResponse = $this->actingAs($admin)
            ->get('/admin/reports/sales?range=this_year')
            ->assertOk()
            ->assertSee('/admin/reports/sales?range=custom&amp;month=2026-11', false);

        foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $monthName) {
            $annualResponse->assertSee($monthName.' 2026');
        }

        $annualResponse->assertViewHas('sales', function (array $sales): bool {
                $this->assertCount(12, $sales['trend']);
                $this->assertSame('Jan 2026', $sales['trend'][0]['label']);
                $this->assertSame('Nov 2026', $sales['trend'][10]['label']);
                $this->assertEquals(55.0, $sales['trend'][10]['value']);
                $this->assertSame('Dec 2026', $sales['trend'][11]['label']);
                return true;
            });
    }

    private function order(User $customer, string $status, string $paymentStatus, float $total, $createdAt): Order
    {
        $order = Order::create([
            'order_number' => 'KJM-REPORT-'.Str::upper(Str::random(8)),
            'user_id' => $customer->id,
            'order_type' => 'pickup',
            'status' => $status,
            'subtotal' => $total,
            'total' => $total,
            'payment_method' => 'qrph',
            'payment_status' => $paymentStatus,
        ]);
        $order->created_at = $createdAt;
        $order->save();
        return $order;
    }

    private function item(Order $order, string $name, int $quantity, float $unitPrice): void
    {
        $order->items()->create([
            'product_name' => $name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $quantity * $unitPrice,
        ]);
    }

    private function refund(Order $order, string $status, int $cents): void
    {
        $paymentId = \DB::table('payments')->insertGetId([
            'order_id' => $order->id, 'method' => 'qrph', 'amount' => $order->total,
            'status' => 'paid', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $requestId = \DB::table('refund_requests')->insertGetId([
            'order_id' => $order->id, 'payment_id' => $paymentId, 'user_id' => $order->user_id,
            'reason' => 'other', 'explanation' => 'Report test', 'status' => 'approved', 'source' => 'customer',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('refund_attempts')->insert([
            'refund_request_id' => $requestId, 'amount_cents' => $cents, 'status' => $status,
            'idempotency_key' => Str::random(64), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function user(string $role): User
    {
        $suffix = Str::lower(Str::random(12));
        return User::create([
            'name' => ucfirst($role).' Report Test', 'username' => $role.$suffix,
            'email' => $suffix.'@example.test', 'password' => 'password', 'role' => $role, 'status' => 'active',
        ]);
    }
}
