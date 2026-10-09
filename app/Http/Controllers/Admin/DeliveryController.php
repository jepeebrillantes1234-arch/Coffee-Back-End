<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    public function index(Request $request)
    {
        $filter=$request->input('filter','active');
        $orders=Order::with(['user:id,name','delivery.rider:id,name','delivery.remittedBy:id,name'])
            ->where('order_type','delivery')
            ->when($filter==='active',fn($q)=>$q->whereIn('status',['ready','rider_assigned','picked_up','on_the_way']))
            ->latest()->paginate(15)->withQueryString();
        return view('admin.deliveries.index',compact('orders','filter'));
    }

    public function remit(Request $request,Delivery $delivery)
    {
        if(!$delivery->cash_collected_at || $delivery->cash_remitted_at) return back()->with('error','Walang bagong COD cash na kailangang i-remit.');
        DB::transaction(function()use($request,$delivery){
            $delivery->update(['cash_remitted_at'=>now(),'cash_remitted_by'=>$request->user()->id]);
            $order=$delivery->order;
            if($order && $order->status==='delivered'){
                $order->update(['status'=>'completed']);
                DB::table('order_status_logs')->insert(['order_id'=>$order->id,'status'=>'completed','changed_by'=>$request->user()->id,'created_at'=>now()]);
                \App\Support\Notifier::order($order->fresh(),'completed');
            }
        });
        return back()->with('success','Naitala ang remittance at nakumpleto ang order.');
    }
}
