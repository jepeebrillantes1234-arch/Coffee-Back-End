<?php
namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\OrderWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index() { $orders=$this->orders(); $pendingCount=Order::whereIn('status',['awaiting_payment','pending'])->count(); return view('staff.orders.index',compact('orders','pendingCount')); }
    public function partial() { $orders=$this->orders(); return response()->view('staff.orders._queue',compact('orders'))->header('X-Pending',Order::whereIn('status',['awaiting_payment','pending'])->count())->header('Cache-Control','no-store, no-cache, must-revalidate'); }
    public function advance(Order $order, Request $request, OrderWorkflow $workflow)
    {
        try {
            $notice = match ($request->input('action')) {
                'confirm' => $workflow->confirm($order),
                'preparing' => $workflow->preparing($order),
                'ready' => $workflow->ready($order),
                'assign_available' => $workflow->assignAvailableRider($order),
                'complete' => $workflow->complete($order),
                default => throw new \DomainException('Hindi kilalang action.'),
            };
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        if (is_string($notice) && $notice !== '') {
            return back()->with('error', $notice);
        }

        return back()->with('success', 'Na-update ang order.');
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

        return back()->with('success', 'Na-assign ang delivery sa online rider.');
    }

    public function remit(Request $request,Delivery $delivery)
    {
        $order=$delivery->order;
        if(!$order || $order->status!=='delivered') return back()->with('error','Delivered na lang ang maaaring i-remit.');
        if($order->payment_method==='cod' && !$delivery->cash_collected_at) return back()->with('error','Wala pang naitalang COD cash mula sa rider.');
        DB::transaction(function()use($request,$delivery,$order){
            if($order->payment_method==='cod' && !$delivery->cash_remitted_at) $delivery->update(['cash_remitted_at'=>now(),'cash_remitted_by'=>$request->user()->id]);
            $order->update(['status'=>'completed']);
            DB::table('order_status_logs')->insert(['order_id'=>$order->id,'status'=>'completed','changed_by'=>$request->user()->id,'created_at'=>now()]);
            \App\Support\Notifier::order($order->fresh(),'completed');
        });
        return back()->with('success','Naitala ang cash handover at nakumpleto ang order.');
    }
    private function orders() { return Order::with(['user:id,name','items','delivery.rider:id,name'])->whereIn('status',['awaiting_payment','pending','confirmed','preparing','ready','rider_assigned','picked_up','on_the_way','delivered'])->latest('id')->limit(100)->get(); }
}
