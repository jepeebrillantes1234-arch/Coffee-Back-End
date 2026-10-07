<?php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

        $orders = Order::with(['user:id,name', 'staff:id,name'])
            ->whereDate('created_at', $date)->orderByDesc('id')->get();

        $done = $orders->whereIn('status', ['delivered', 'completed']);

        $summary = [
            'sales'      => $done->sum('total'),
            'count'      => $done->count(),
            'discounts'  => $done->sum('discount'),
            'active'     => $orders->whereNotIn('status', ['completed', 'delivered', 'cancelled', 'rejected'])->count(),
            'cancelled'  => $orders->whereIn('status', ['cancelled', 'rejected'])->count(),
            // Pera sa counter: walk-in at pickup na bayad
            'counter_cash' => $done->whereIn('order_type', ['walkin', 'pickup'])->where('payment_status', 'paid')->where('payment_method', 'cash')->sum('total'),
            // COD na kinolekta ng riders
            'cod'        => $done->where('order_type', 'delivery')->where('payment_status', 'paid')->sum('total'),
        ];

        $byType = $done->groupBy('order_type')->map(fn ($g) => ['n' => $g->count(), 'sum' => $g->sum('total')]);

        $byCashier = $done->where('order_type', 'walkin')->groupBy(fn ($o) => optional($o->staff)->name ?? '—')
            ->map(fn ($g) => ['n' => $g->count(), 'sum' => $g->sum('total')]);

        return view('staff.transactions.index', compact('date', 'orders', 'summary', 'byType', 'byCashier'));
    }
}
