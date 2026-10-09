<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const DONE = ['delivered', 'completed'];

    public function show(Request $request, string $type)
    {
        abort_unless(in_array($type, ['sales', 'products', 'riders', 'customers']), 404);

        $from = $request->input('from', now()->subDays(29)->toDateString());
        $to   = $request->input('to', now()->toDateString());

        [$title, $columns, $rows] = $this->build($type, $from, $to);

        if ($request->input('export') === 'csv') {
            return response()->streamDownload(function () use ($columns, $rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM para tama sa Excel
                fputcsv($out, $columns);
                foreach ($rows as $r) fputcsv($out, $r);
                fclose($out);
            }, "report-{$type}-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
        }

        return view('admin.reports.show', compact('type', 'title', 'columns', 'rows', 'from', 'to'));
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
                return ['Sales Report', ['Petsa', 'Orders', 'Subtotal', 'Discount', 'Delivery Fee', 'Total'], $rows];

            case 'products':
                $rows = DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
                    ->whereIn('o.status', self::DONE)
                    ->whereRaw('DATE(o.created_at) BETWEEN ? AND ?', [$from, $to])
                    ->selectRaw('oi.product_name, SUM(oi.quantity) as qty, SUM(oi.total_price) as revenue')
                    ->groupBy('oi.product_name')->orderByDesc('qty')->get()
                    ->map(fn ($r) => [$r->product_name, $r->qty, $m($r->revenue)])->all();
                return ['Product Report', ['Product', 'Naibenta (qty)', 'Kita'], $rows];

            case 'riders':
                $rows = DB::table('deliveries as d')->join('users as u', 'u.id', '=', 'd.rider_id')
                    ->where('d.status', 'delivered')
                    ->whereRaw('DATE(d.delivered_at) BETWEEN ? AND ?', [$from, $to])
                    ->selectRaw('u.name, COUNT(*) as trips, SUM(COALESCE(d.distance_km,0)) as km')
                    ->groupBy('u.id', 'u.name')->orderByDesc('trips')->get()
                    ->map(fn ($r) => [$r->name, $r->trips, $m($r->km)])->all();
                return ['Rider Report', ['Rider', 'Delivered na trips', 'Kabuuang km'], $rows];

            default:
                $rows = DB::table('orders as o')->join('users as u', 'u.id', '=', 'o.user_id')
                    ->whereIn('o.status', self::DONE)
                    ->whereRaw('DATE(o.created_at) BETWEEN ? AND ?', [$from, $to])
                    ->selectRaw('u.name, u.email, COUNT(*) as n, SUM(o.total) as spent')
                    ->groupBy('u.id', 'u.name', 'u.email')->orderByDesc('spent')->get()
                    ->map(fn ($r) => [$r->name, $r->email, $r->n, $m($r->spent)])->all();
                return ['Customer Report', ['Customer', 'Email', 'Orders', 'Nagastos'], $rows];
        }
    }
}
