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

    public function cancellations(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:120',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'reason' => 'nullable|in:Change Address,Change Payment Method,Wrong Order,Others',
        ]);
        $latestCancellation = DB::table('order_status_logs')
            ->selectRaw('order_id, MAX(created_at) as cancelled_at')
            ->where('status', 'cancelled')
            ->groupBy('order_id');

        $query = Order::query()
            ->leftJoinSub($latestCancellation, 'cancellation_logs', function ($join) {
                $join->on('cancellation_logs.order_id', '=', 'orders.id');
            })
            ->with('user:id,name,email')
            ->where('orders.status', 'cancelled')
            ->select('orders.*')
            ->selectRaw('COALESCE(cancellation_logs.cancelled_at, orders.updated_at) as cancelled_at');

        $applyFilters = function ($builder, bool $includeReason = true) use ($request) {
            if ($request->filled('search')) {
                $search = trim((string) $request->input('search'));
                $builder->where(function ($q) use ($search) {
                    $q->where('orders.order_number', 'like', "%{$search}%")
                        ->orWhere('orders.reject_reason', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($user) use ($search) {
                            $user->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            if ($request->filled('from')) {
                $builder->whereDate(DB::raw('COALESCE(cancellation_logs.cancelled_at, orders.updated_at)'), '>=', $request->input('from'));
            }
            if ($request->filled('to')) {
                $builder->whereDate(DB::raw('COALESCE(cancellation_logs.cancelled_at, orders.updated_at)'), '<=', $request->input('to'));
            }
            if ($includeReason && $request->filled('reason')) {
                $this->filterCancellationReason($builder, (string) $request->input('reason'));
            }
        };
        $applyFilters($query);

        $counts = [];
        foreach (['Change Address', 'Change Payment Method', 'Wrong Order', 'Others'] as $reason) {
            $countQuery = Order::query()
                ->leftJoinSub($latestCancellation, 'cancellation_logs', function ($join) {
                    $join->on('cancellation_logs.order_id', '=', 'orders.id');
                })
                ->where('orders.status', 'cancelled');
            $applyFilters($countQuery, false);
            $this->filterCancellationReason($countQuery, $reason);
            $counts[$reason] = $countQuery->count('orders.id');
        }

        $orders = $query->orderByDesc(DB::raw('COALESCE(cancellation_logs.cancelled_at, orders.updated_at)'))
            ->paginate(15)->withQueryString();

        return view('admin.orders.cancellations', compact('orders', 'counts'));
    }

    private function filterCancellationReason($query, string $reason): void
    {
        $prefixes = [
            'Change Address' => ['Change Address', 'CHANGE_ADDRESS'],
            'Change Payment Method' => ['Change Payment Method', 'CHANGE_PAYMENT_METHOD'],
            'Wrong Order' => ['Wrong Order', 'WRONG_ORDER'],
        ];

        if (isset($prefixes[$reason])) {
            $query->where(function ($q) use ($prefixes, $reason) {
                foreach ($prefixes[$reason] as $prefix) {
                    $q->orWhere('orders.reject_reason', 'like', $prefix.'%');
                }
            });
            return;
        }

        if ($reason === 'Others') {
            $query->where(function ($q) use ($prefixes) {
                foreach (array_merge(...array_values($prefixes)) as $prefix) {
                    $q->where(function ($nonMatch) use ($prefix) {
                        $nonMatch->whereNull('orders.reject_reason')
                            ->orWhere('orders.reject_reason', 'not like', $prefix.'%');
                    });
                }
            });
        }
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
