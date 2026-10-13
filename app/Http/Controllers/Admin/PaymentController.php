<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with('user:id,name')
            ->whereNotIn('status', ['rejected', 'cancelled', 'awaiting_payment'])
            ->when($request->payment_status, fn ($q, $v) => $q->where('payment_status', $v))
            ->when($request->payment_method, fn ($q, $v) => $q->where('payment_method', $v))
            ->latest()->paginate(15)->withQueryString();

        $summary = [
            'paid_today' => DB::table('orders')->where('payment_status', 'paid')->whereDate('updated_at', now()->toDateString())->sum('total'),
            'unpaid'     => DB::table('orders')->where('payment_status', 'unpaid')->whereNotIn('status', ['rejected', 'cancelled', 'awaiting_payment'])->sum('total'),
            'refunded'   => DB::table('orders')->where('payment_status', 'refunded')->sum('total'),
        ];

        return view('admin.payments.index', compact('orders', 'summary'));
    }

    public function markPaid(Order $order)
    {
        if ($order->payment_method === 'qrph') return back()->with('error', 'Awtomatikong nate-track ang QRPh. Hindi puwedeng i-mark nang mano-mano.');
        if ($order->payment_status !== 'unpaid') return back()->with('error', 'Hindi ito unpaid.');
        $this->setStatus($order, 'paid');
        Activity::log('payment', 'payments', "Minarkahang paid ang {$order->order_number}");
        return back()->with('success', 'Minarkahang paid.');
    }

    public function refund(Order $order)
    {
        if ($order->payment_status !== 'paid') return back()->with('error', 'Paid lang ang puwedeng i-refund.');
        if ($order->payment_method === 'qrph') {
            try {
                app(\App\Services\PaymentGateway::class)->refundOrder($order);
            } catch (\Throwable $e) {
                report($e);
                return back()->with('error', 'Hindi na-refund: ' . $e->getMessage());
            }
        } else {
            $this->setStatus($order, 'refunded');
        }
        Activity::log('refund', 'payments', "Na-refund ang {$order->order_number}");
        return back()->with('success', 'Na-refund ang bayad.');
    }

    private function setStatus(Order $order, string $status): void
    {
        $order->update(['payment_status' => $status]);
        Payment::updateOrCreate(['order_id' => $order->id], [
            'method' => $order->payment_method, 'amount' => $order->total,
            'status' => $status, 'paid_at' => $status === 'paid' ? now() : null,
        ]);
    }
}
