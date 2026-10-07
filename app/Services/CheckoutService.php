<?php
namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function placeWalkIn($staff,array $payload,float $cash): Order
    {
        $lines=[];$subtotal=0;
        foreach($payload['items'] as $line){$p=Product::where('is_available',true)->findOrFail($line['product_id']);$qty=(int)$line['quantity'];$price=(float)$p->base_price;$total=round($qty*$price,2);$subtotal+=$total;$lines[]=[$p,$qty,$price,$total];}
        $total=round($subtotal,2); if($cash<$total) throw new \DomainException('Kulang ang cash na natanggap.');
        return DB::transaction(function()use($staff,$payload,$lines,$subtotal,$total,$cash){
            $o=Order::create(['order_number'=>'KJM-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),'staff_id'=>$staff->id,'order_type'=>'walkin','status'=>'preparing','subtotal'=>$subtotal,'discount'=>0,'delivery_fee'=>0,'total'=>$total,'payment_method'=>'cash','payment_status'=>'paid','notes'=>$payload['notes']??null]);
            foreach($lines as [$p,$qty,$price,$sum])$o->items()->create(['product_id'=>$p->id,'product_name'=>$p->name,'quantity'=>$qty,'unit_price'=>$price,'total_price'=>$sum]);
            Payment::create(['order_id'=>$o->id,'method'=>'cash','amount'=>$total,'reference_no'=>'cash='.number_format($cash,2,'.','').';change='.number_format($cash-$total,2,'.',''),'status'=>'paid','paid_at'=>now()]);
            DB::table('order_status_logs')->insert(['order_id'=>$o->id,'status'=>'preparing','changed_by'=>$staff->id,'created_at'=>now()]);return $o;
        });
    }
}
