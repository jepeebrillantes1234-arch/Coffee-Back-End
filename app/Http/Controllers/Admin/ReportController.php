<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const DONE = ['delivered', 'completed'];

    public function show(Request $request, string $type)
    {
        abort_unless(in_array($type, ['sales', 'products', 'riders', 'customers']), 404);

        $timezone = 'Asia/Manila';
        $range = $request->input('range', $request->hasAny(['from', 'to']) ? 'custom' : 'this_month');
        $request->validate(['range' => ['sometimes', Rule::in(['today', 'yesterday', 'this_week', 'this_month', 'this_year', 'custom'])]]);
        $trendUnit = 'monthly';
        [$from, $to] = $this->dateRange($request, $range, $timezone);
        $trendYear = $request->filled('month')
            ? (int) substr((string) $request->input('month'), 0, 4)
            : (int) Carbon::parse($from, $timezone)->format('Y');
        $startUtc = Carbon::parse($from, $timezone)->startOfDay()->utc();
        $endUtc = Carbon::parse($to, $timezone)->endOfDay()->utc();

        if ($type === 'sales') {
            $sales = $this->salesData($startUtc, $endUtc, $from, $to, $timezone, $trendUnit, $trendYear);
            $title = 'Sales Statistics';
            $columns = ['Order', 'Date / Time', 'Customer', 'Products', 'Subtotal', 'Delivery Fee', 'Discount', 'Payment', 'Payment Status', 'Order Status', 'Refunded', 'Final Total'];
            $rows = collect($sales['transactions'])->map(fn ($transaction) => [
                $transaction['number'], $transaction['date'], $transaction['customer'],
                $transaction['items']->map(fn ($item) => $item->product_name.' x '.$item->quantity.' @ '.$item->unit_price)->implode('; '),
                $transaction['subtotal'], $transaction['delivery_fee'], $transaction['discount'],
                $transaction['payment_method'], $transaction['payment_status'], $transaction['status'],
                $transaction['refunded'], $transaction['total'],
            ])->all();
        } else {
            [$title, $columns, $rows] = $this->build($type, $from, $to);
            $sales = null;
        }

        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($columns, $rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM para tama sa Excel
                fputcsv($out, $columns);
                foreach ($rows as $r) fputcsv($out, $r);
                fclose($out);
            }, "report-{$type}-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
        }

        if ($request->input('export') === 'pdf') {
            abort_unless($type === 'sales', 404);

            $pdf = Pdf::loadView('admin.reports.sales_pdf', compact('sales', 'from', 'to', 'trendYear'))
                ->setPaper('a4', 'landscape');
            $pdf->render();
            $pdf->getDomPDF()->getCanvas()->page_text(735, 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.35, 0.27, 0.22]);

            return $pdf->download("kjm-sales-report-{$from}-to-{$to}.pdf");
        }

        $selectedMonth = $request->input('month');
        if (! $selectedMonth && substr($from, 0, 7) === substr($to, 0, 7)) {
            $selectedMonth = substr($from, 0, 7);
        }
        $selectedMonth ??= Carbon::now($timezone)->format('Y-m');

        return view('admin.reports.show', compact('type', 'title', 'columns', 'rows', 'from', 'to', 'range', 'sales', 'trendUnit', 'selectedMonth', 'trendYear'));
    }

    private function dateRange(Request $request, string $range, string $timezone): array
    {
        $today = Carbon::now($timezone)->startOfDay();
        if ($request->filled('month')) {
            $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
            $month = Carbon::createFromFormat('!Y-m', $data['month'], $timezone);
            return [$month->copy()->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()];
        }
        if ($range === 'today') return [$today->toDateString(), $today->toDateString()];
        if ($range === 'yesterday') {
            $day = $today->copy()->subDay()->toDateString();
            return [$day, $day];
        }
        if ($range === 'this_week') return [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()];
        if ($range === 'this_month') return [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()];
        if ($range === 'this_year') return [$today->copy()->startOfYear()->toDateString(), $today->copy()->endOfYear()->toDateString()];

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        return [$data['from'], $data['to']];
    }

    private function salesData(Carbon $startUtc, Carbon $endUtc, string $from, string $to, string $timezone, string $trendUnit, int $trendYear): array
    {
        $orders = Order::query()
            ->whereIn('status', self::DONE)
            ->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->with([
                'user:id,name',
                'items:id,order_id,product_name,quantity,unit_price,total_price',
                'payments:id,order_id,method,amount,status,reference_no,provider,provider_payment_id,paid_at',
            ])
            ->orderBy('created_at')->get();
        $orderIds = $orders->modelKeys();
        $refunds = $orderIds === [] ? collect() : DB::table('refund_attempts as attempt')
            ->join('refund_requests as request', 'request.id', '=', 'attempt.refund_request_id')
            ->where('attempt.status', 'succeeded')->whereIn('request.order_id', $orderIds)
            ->selectRaw('request.order_id, SUM(attempt.amount_cents) AS cents')
            ->groupBy('request.order_id')->pluck('cents', 'request.order_id');

        $gross = (float) $orders->sum('total');
        $refundTotal = (float) $refunds->sum() / 100;
        $quantity = (int) $orders->sum(fn ($order) => $order->items->sum('quantity'));
        $orderCount = $orders->count();

        $products = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $name = $item->product_name ?: 'Deleted product';
                $products[$name] ??= ['name' => $name, 'quantity' => 0, 'revenue' => 0.0];
                $products[$name]['quantity'] += (int) $item->quantity;
                $products[$name]['revenue'] += (float) $item->total_price;
            }
        }
        $trendStart = Carbon::create($trendYear, 1, 1, 0, 0, 0, $timezone)->utc();
        $trendEnd = Carbon::create($trendYear, 12, 31, 23, 59, 59, $timezone)->utc();
        $trendOrders = Order::query()
            ->whereIn('status', self::DONE)
            ->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->whereBetween('created_at', [$trendStart, $trendEnd])
            ->get(['created_at', 'total']);
        $monthlySales = [];
        foreach ($trendOrders as $trendOrder) {
            $localDate = Carbon::parse($trendOrder->created_at, 'UTC')->timezone($timezone);
            $monthKey = $this->trendBucket($localDate, $from, $to, $trendUnit);
            $monthlySales[$monthKey] = ($monthlySales[$monthKey] ?? 0) + (float) $trendOrder->total;
        }
        $trend = [];
        $monthCursor = Carbon::create($trendYear, 1, 1, 0, 0, 0, $timezone);
        $lastMonth = Carbon::create($trendYear, 12, 1, 0, 0, 0, $timezone);
        while ($monthCursor->lte($lastMonth)) {
            $monthKey = $monthCursor->format('Y-m');
            $trend[] = [
                'label' => $monthCursor->format('M Y'),
                'month' => $monthKey,
                'value' => (float) ($monthlySales[$monthKey] ?? 0),
            ];
            $monthCursor->addMonth();
        }
        $products = collect($products)->values();
        $topProducts = $products->sortByDesc('quantity')->take(10)->values();
        $leastProducts = $products->sortBy('quantity')->take(10)->values();
        $maxQuantity = max(1, (int) $products->max('quantity'));
        $maxRevenue = max(1, (float) $products->max('revenue'));

        $transactions = $orders->map(function ($order) use ($refunds, $timezone) {
            return [
                'number' => $order->order_number,
                'date' => Carbon::parse($order->created_at, 'UTC')->timezone($timezone)->format('M j, Y g:i A'),
                'customer' => $order->user?->name ?? 'Deleted customer',
                'items' => $order->items,
                'payments' => $order->payments,
                'subtotal' => (float) $order->subtotal,
                'delivery_fee' => (float) $order->delivery_fee,
                'discount' => (float) $order->discount,
                'payment_method' => strtoupper((string) $order->payment_method),
                'payment_status' => ucfirst(str_replace('_', ' ', (string) $order->payment_status)),
                'status' => ucfirst(str_replace('_', ' ', (string) $order->status)),
                'total' => (float) $order->total,
                'refunded' => (float) ($refunds[$order->id] ?? 0) / 100,
            ];
        });

        return [
            'gross' => $gross,
            'orders' => $orderCount,
            'quantity' => $quantity,
            'average' => $orderCount ? $gross / $orderCount : 0,
            'refunds' => $refundTotal,
            'net' => $gross - $refundTotal,
            'transactions' => $transactions,
            'products' => $topProducts->map(fn ($product, $index) => [...$product, 'rank' => $index + 1,
                'quantity_width' => ((int) $product['quantity'] / $maxQuantity) * 100,
                'revenue_width' => ((float) $product['revenue'] / $maxRevenue) * 100])->all(),
            'least_products' => $leastProducts->all(),
            'trend' => $trend,
            'period' => "{$from} to {$to} (Asia/Manila)",
        ];
    }

    private function trendBucket(Carbon $date, string $from, string $to, string $unit): string
    {
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to));
        if ($unit === 'daily' || ($unit === 'auto' && $days <= 31)) return $date->format('M j');
        if ($unit === 'weekly' || ($unit === 'auto' && $days <= 180)) return 'Week of ' . $date->copy()->startOfWeek()->format('M j');
        if ($unit === 'monthly' || ($unit === 'auto' && $days <= 1095)) return $date->format('Y-m');
        if ($unit === 'yearly') return $date->format('Y');
        return $date->format('Y');
    }

    private function build(string $type, string $from, string $to): array
    {
        $m = fn ($v) => number_format((float) $v, 2, '.', '');

        switch ($type) {
            case 'sales':
                $rows = DB::table('orders')->whereIn('status', self::DONE)
                    ->whereRaw('DATE(created_at) BETWEEN ? AND ?', [$from, $to])
                    ->selectRaw('DATE(created_at) as d, COUNT(*) as n, SUM(subtotal) as sub, SUM(discount) as disc, SUM(delivery_fee) as fee, SUM(total) as total')
                    ->groupByRaw('DATE(created_at)')->orderByRaw('DATE(created_at)')->get()
                    ->map(fn ($r) => [$r->d, $r->n, $m($r->sub), $m($r->disc), $m($r->fee), $m($r->total)])->all();
                return ['Sales Report', ['Date', 'Orders', 'Subtotal', 'Discount', 'Delivery Fee', 'Total'], $rows];

            case 'products':
                $rows = DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
                    ->whereIn('o.status', self::DONE)
                    ->whereIn('o.payment_status', ['paid', 'partially_refunded', 'refunded'])
                    ->whereRaw('DATE(o.created_at) BETWEEN ? AND ?', [$from, $to])
                    ->selectRaw('oi.product_name, SUM(oi.quantity) as qty, SUM(oi.total_price) as revenue')
                    ->groupBy('oi.product_name')->orderByDesc('qty')->get()
                    ->map(fn ($r) => [$r->product_name, $r->qty, $m($r->revenue)])->all();
                return ['Product Report', ['Product', 'Quantity Sold', 'Revenue'], $rows];

            case 'riders':
                $rows = DB::table('deliveries as d')->join('users as u', 'u.id', '=', 'd.rider_id')
                    ->where('d.status', 'delivered')
                    ->whereRaw('DATE(d.delivered_at) BETWEEN ? AND ?', [$from, $to])
                    ->selectRaw('u.name, COUNT(*) as trips, SUM(COALESCE(d.distance_km,0)) as km')
                    ->groupBy('u.id', 'u.name')->orderByDesc('trips')->get()
                    ->map(fn ($r) => [$r->name, $r->trips, $m($r->km)])->all();
                return ['Rider Report', ['Rider', 'Deliveries Completed', 'Total Distance (km)'], $rows];

            default:
                $rows = DB::table('orders as o')->join('users as u', 'u.id', '=', 'o.user_id')
                    ->whereIn('o.status', self::DONE)
                    ->whereIn('o.payment_status', ['paid', 'partially_refunded', 'refunded'])
                    ->whereRaw('DATE(o.created_at) BETWEEN ? AND ?', [$from, $to])
                    ->selectRaw('u.name, u.email, COUNT(*) as n, SUM(o.total) as spent')
                    ->groupBy('u.id', 'u.name', 'u.email')->orderByDesc('spent')->get()
                    ->map(fn ($r) => [$r->name, $r->email, $r->n, $m($r->spent)])->all();
                return ['Customer Report', ['Customer', 'Email', 'Orders', 'Total Spent'], $rows];
        }
    }
}
