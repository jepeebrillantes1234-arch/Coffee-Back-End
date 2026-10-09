<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $done  = ['delivered', 'completed'];
        $today = now()->toDateString();

        $stats = [
            'sales_today'    => DB::table('orders')->whereDate('created_at', $today)->whereIn('status', $done)->sum('total'),
            'orders_today'   => DB::table('orders')->whereDate('created_at', $today)->count(),
            'pending_orders' => DB::table('orders')->where('status', 'pending')->count(),
            'active_riders'  => DB::table('rider_profiles')->where('is_available', true)->count(),
            'customers'      => DB::table('users')->where('role', 'customer')->count(),
            'products'       => DB::table('products')->count(),
        ];

        $days  = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $sales = DB::table('orders')->whereIn('status', $done)
            ->whereDate('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) as d, SUM(total) as t')
            ->groupByRaw('DATE(created_at)')->pluck('t', 'd');
        $chart = $days->map(fn ($d) => ['label' => Carbon::parse($d)->format('D'), 'total' => (float) ($sales[$d] ?? 0)]);

        $topProducts = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereIn('o.status', $done)
            ->whereDate('o.created_at', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('oi.product_name, SUM(oi.quantity) as qty')
            ->groupBy('oi.product_name')->orderByDesc('qty')->limit(5)->get();

        $recent = Order::with('user:id,name')->latest()->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'chart', 'topProducts', 'recent'));
    }
}
