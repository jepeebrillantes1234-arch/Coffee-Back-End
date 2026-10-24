<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:120',
            'method' => 'nullable|in:qrph,cash,cod',
            'status' => 'nullable|in:pending,paid,failed,expired,refunded',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);
        $payments = Payment::query()->with(['order.user:id,name'])
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'rejected'))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim($request->string('q')->toString());
                $query->where(function ($q) use ($term) {
                    $q->where('provider_payment_id', 'like', "%{$term}%")
                        ->orWhere('reference_no', 'like', "%{$term}%")
                        ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$term}%")
                            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%")));
                });
            })
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->input('method')))
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = $request->input('status');
                $q->whereHas('order', function ($order) use ($status) {
                    if ($status === 'paid') $order->where('payment_status', 'paid');
                    elseif ($status === 'refunded') $order->where('payment_status', 'refunded');
                    elseif ($status === 'pending') $order->where('payment_status', 'unpaid');
                    else $order->whereHas('payments', fn ($p) => $p->where('status', $status));
                });
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest('id')->paginate(20)->withQueryString();

        $summary = [
            'paid_today' => DB::table('payments')->where('status', 'paid')->whereDate('paid_at', now()->toDateString())->sum('amount'),
            'pending' => DB::table('payments')->where('status', 'pending')->sum('amount'),
            'refunded' => DB::table('payments')->where('status', 'refunded')->sum('amount'),
        ];

        return view('admin.payments.index', compact('payments', 'summary'));
    }

    public function settings()
    {
        $configured = config('paymongo.mode') === 'simulated' || filled(config('paymongo.secret'));
        $qrphAvailable = config('shop.payment', 'cod') === 'online' && $configured;
        return view('admin.payments.settings', [
            'gatewayConfigured' => $configured && config('paymongo.mode') !== 'simulated',
            'simulated' => config('paymongo.mode') === 'simulated',
            'qrphAvailable' => $qrphAvailable,
            'qrphEnabled' => (bool) ((int) Settings::get('payment_qrph_enabled', 1)) && $qrphAvailable,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $configured = config('shop.payment', 'cod') === 'online'
            && (config('paymongo.mode') === 'simulated' || filled(config('paymongo.secret')));
        if (!$configured) {
            return back()->with('error', 'QRPh cannot be enabled until the existing PayMongo checkout is configured and enabled.');
        }
        Settings::set('payment_qrph_enabled', $request->boolean('qrph_enabled') ? '1' : '0');
        Activity::log('update', 'payments', 'Updated QRPh availability');
        return back()->with('success', 'Payment method settings saved.');
    }

    public function markPaid(Order $order)
    {
        if ($order->payment_method === 'qrph') return back()->with('error', 'QRPh payments are verified automatically and cannot be marked paid manually.');
        if ($order->payment_status !== 'unpaid') return back()->with('error', 'This order is not unpaid.');
        $this->setStatus($order, 'paid');
        Activity::log('payment', 'payments', "Marked paid: {$order->order_number}");
        return back()->with('success', 'Payment marked paid.');
    }

    public function refund(Order $order)
    {
        if ($order->payment_status !== 'paid') return back()->with('error', 'Only paid orders can be refunded.');
        if ($order->payment_method === 'qrph') {
            try { app(\App\Services\PaymentGateway::class)->refundOrder($order); }
            catch (\Throwable $e) {
                Log::error('Payment refund failed', ['order_id' => $order->id, 'exception' => get_class($e)]);
                return back()->with('error', 'Refund could not be completed. Check the payment provider dashboard.');
            }
        } else {
            $this->setStatus($order, 'refunded');
        }
        Activity::log('refund', 'payments', "Refunded: {$order->order_number}");
        return back()->with('success', 'Payment refunded.');
    }

    private function setStatus(Order $order, string $status): void
    {
        DB::transaction(function () use ($order, $status) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $locked->update(['payment_status' => $status]);
            Payment::updateOrCreate(['order_id' => $locked->id], [
                'method' => $locked->payment_method, 'amount' => $locked->total,
                'status' => $status, 'paid_at' => $status === 'paid' ? now() : null,
            ]);
        });
    }
}
