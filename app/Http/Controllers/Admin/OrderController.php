<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWorkflow;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with('user:id,name')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->order_type, fn ($q, $v) => $q->where('order_type', $v))
            ->when($request->search, fn ($q, $s) => $q->where('order_number', 'like', "%$s%"))
            ->latest('id')->paginate(15)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['user', 'staff', 'items.options', 'delivery.rider', 'payments', 'review']);

        $logs = DB::table('order_status_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.changed_by')
            ->where('l.order_id', $order->id)
            ->select('l.status', 'l.created_at', 'u.name as by')
            ->orderBy('l.id')->get();

        $riders = User::where('role', 'rider')->where('status', 'active')
            ->whereHas('riderProfile', fn ($q) => $q->where('is_approved', true)->where('is_available', true))
            ->orderBy('name')->get(['id', 'name']);

        $shop = ['lat' => (float) Settings::get('shop_lat', 0), 'lng' => (float) Settings::get('shop_lng', 0)];

        return view('admin.orders.show', compact('order', 'logs', 'riders', 'shop'));
    }

    public function confirm(Order $order, OrderWorkflow $wf)
    {
        return $this->run($order, fn () => $wf->confirm($order), 'confirm', 'Nakumpirma ang order.');
    }

    public function preparing(Order $order, OrderWorkflow $wf)
    {
        return $this->run($order, fn () => $wf->preparing($order), 'preparing', 'Inihahanda na ang order.');
    }

    public function ready(Order $order, OrderWorkflow $wf)
    {
        return $this->run($order, fn () => $wf->ready($order), 'ready', 'Handa na ang order.');
    }

    public function complete(Order $order, OrderWorkflow $wf)
    {
        return $this->run($order, fn () => $wf->complete($order), 'complete', 'Tapos na ang order.');
    }

    public function reject(Request $request, Order $order, OrderWorkflow $wf)
    {
        $request->validate(['reason' => 'required|string|max:255']);
        return $this->run($order, fn () => $wf->reject($order, $request->reason), 'reject', 'Tinanggihan ang order.');
    }

    public function assign(Request $request, Order $order, OrderWorkflow $wf)
    {
        $request->validate(['rider_id' => 'required|integer|exists:users,id']);
        return $this->run($order, fn () => $wf->assignRider($order, (int) $request->rider_id), 'assign-rider', 'Na-assign ang rider.');
    }

    public function cancel(Request $request, Order $order, OrderWorkflow $wf)
    {
        $request->validate(['reason' => 'required|string|max:255']);
        return $this->run($order, fn () => $wf->cancel($order, $request->reason), 'cancel', 'Na-cancel ang order.');
    }

    private function run(Order $order, \Closure $fn, string $action, string $okMsg)
    {
        try {
            $notice = $fn();
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        Activity::log($action, 'orders', "{$order->order_number}: {$action}");
        $resp = back()->with('success', $okMsg);
        return (is_string($notice) && $notice !== '') ? $resp->with('error', $notice) : $resp;
    }
}
