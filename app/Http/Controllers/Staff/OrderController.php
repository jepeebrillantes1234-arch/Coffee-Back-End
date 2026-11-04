<?php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\OrderWorkflow;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index() { $orders=$this->orders(); $pendingCount=Order::whereIn('status',['awaiting_payment','pending'])->count(); return view('staff.orders.index',compact('orders','pendingCount')); }
    public function partial() { $orders=$this->orders(); return response()->view('staff.orders._queue',compact('orders'))->header('X-Pending',Order::whereIn('status',['awaiting_payment','pending'])->count())->header('Cache-Control','no-store, no-cache, must-revalidate'); }
    public function count() { return response()->json(['count'=>Order::whereIn('status',['awaiting_payment','pending'])->count()])->header('Cache-Control','no-store, no-cache, must-revalidate'); }
    public function alerts()
    {
        $statuses = ['awaiting_payment', 'pending'];
        $count = Order::whereIn('status', $statuses)->count();
        $items = Order::with('user:id,name')
            ->whereIn('status', $statuses)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn ($order) => [
                'order_number' => $order->order_number,
                'customer' => $order->user?->name ?? 'Customer',
                'status' => strtoupper(str_replace('_', ' ', $order->status)),
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->diffForHumans(),
            ])
            ->values();

        return response()->json(['count' => $count, 'items' => $items])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
    public function advance(Order $order, Request $request, OrderWorkflow $workflow)
    {
        $previousStatus = $order->status;
        $action = (string) $request->input('action');
        try {
            $notice = match ($request->input('action')) {
                'confirm' => $workflow->confirm($order),
                'preparing' => $workflow->preparing($order),
                'ready' => $workflow->ready($order),
                'assign_available' => $workflow->assignAvailableRider($order),
                'complete' => $workflow->complete($order),
                default => throw new \DomainException('Unknown order action.'),
            };
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (is_string($notice) && $notice !== '') {
            if ($order->fresh()->status !== $previousStatus) {
                Activity::log($action, 'orders', "{$order->order_number} (#{$order->id}): {$previousStatus} -> {$order->fresh()->status}");
            }
            return back()->with('error', $notice);
        }

        $newStatus = $order->fresh()->status;
        $details = "{$order->order_number} (#{$order->id}): {$previousStatus} -> {$newStatus}";
        if ($action === 'assign_available') {
            $details .= '; rider #'.(Delivery::where('order_id', $order->id)->value('rider_id') ?? 'unassigned');
        }
        Activity::log($action, 'orders', $details);

        return back()->with('success', 'Order updated.');
    }

    public function assignAvailable(Order $order, OrderWorkflow $workflow)
    {
        try {
            $notice = $workflow->assignAvailableRider($order);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (is_string($notice) && $notice !== '') {
            return back()->with('error', $notice);
        }

        $riderId = Delivery::where('order_id', $order->id)->value('rider_id');
        Activity::log('assign_available', 'deliveries', "Order {$order->order_number} (#{$order->id}): ready -> rider_assigned; rider #{$riderId}.");

        return back()->with('success', 'Delivery assigned to an online rider.');
    }

    public function remit(Request $request,Delivery $delivery)
    {
        $order=$delivery->order;
        if(!$order || $order->status!=='delivered') return back()->with('error','Only delivered orders can be remitted.');
        if($order->payment_method==='cod' && !$delivery->cash_collected_at) return back()->with('error','The rider has not recorded the COD cash collection yet.');
        DB::transaction(function()use($request,$delivery,$order){
            if($order->payment_method==='cod' && !$delivery->cash_remitted_at) $delivery->update(['cash_remitted_at'=>now(),'cash_remitted_by'=>$request->user()->id]);
            $order->update(['status'=>'completed']);
            DB::table('order_status_logs')->insert(['order_id'=>$order->id,'status'=>'completed','changed_by'=>$request->user()->id,'created_at'=>now()]);
            \App\Support\Notifier::order($order->fresh(),'completed');
        });
        Activity::log('remit', 'deliveries', "Order {$order->order_number} (#{$order->id}) completed after rider cash handover.");
        return back()->with('success','Cash handover recorded and order completed.');
    }
    private function orders() { return Order::with(['user:id,name,phone','items.product:id,name,image','items.options','delivery.rider:id,name'])->whereIn('status',['awaiting_payment','pending','confirmed','preparing','ready','rider_assigned','picked_up','on_the_way','delivered'])->latest('id')->limit(100)->get(); }
}
