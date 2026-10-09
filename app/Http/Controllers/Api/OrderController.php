<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\PromoUsage;
use App\Models\User;
use App\Services\PaymentGateway;
use App\Support\Geo;
use App\Support\Notifier;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use Responds;

    public function preview(Request $request)
    {
        $data=$this->validateCheckout($request,false);
        $quote=$this->quote($request->user(),$data);
        return $this->ok(['items'=>collect($quote['lines'])->map(fn($line)=>[
            'qty'=>$line['quantity'],'name'=>$line['product']->name,'line_total'=>$line['total'],
        ])->values(),'subtotal'=>$quote['subtotal'],'discount'=>$quote['discount'],'discount_label'=>$quote['discount_label'],
            'distance_km'=>$quote['distance_km'],'delivery_fee'=>$quote['delivery_fee'],'total'=>$quote['total']]);
    }

    public function store(Request $request)
    {
        $data=$this->validateCheckout($request,true);
        $method=$data['payment_method']??(($data['order_type']==='delivery')?(config('shop.payment','cod')==='online'?'gcash':'cod'):(config('shop.payment','cod')==='online'?'gcash':'cash'));
        if($method==='gcash' && config('shop.payment','cod')!=='online') return $this->fail('COD lang ang kasalukuyang paraan ng bayad.');
        if($data['order_type']==='delivery' && $method==='cash') return $this->fail('COD o GCash lang ang puwedeng bayad para sa delivery.');
        if($data['order_type']==='pickup' && $method==='cod') return $this->fail('Cash sa counter o GCash ang bayad para sa pickup.');
        $quote=$this->quote($request->user(),$data);
        $order=DB::transaction(function()use($request,$data,$method,$quote){
            $o=Order::create(['order_number'=>'KJM-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id'=>$request->user()->id,'order_type'=>$data['order_type'],'status'=>$method==='gcash'?'awaiting_payment':'pending',
                'subtotal'=>$quote['subtotal'],'discount'=>$quote['discount'],'delivery_fee'=>$quote['delivery_fee'],'total'=>$quote['total'],
                'is_first_order'=>$quote['is_first_order'],'payment_method'=>$method,'payment_status'=>'unpaid',
                'delivery_address'=>$data['delivery_address']??null,'delivery_lat'=>$data['delivery_lat']??null,
                'delivery_lng'=>$data['delivery_lng']??null,'notes'=>$data['notes']??null]);
            foreach($quote['lines'] as $line){
                $item=$o->items()->create(['product_id'=>$line['product']->id,'product_name'=>$line['product']->name,
                    'quantity'=>$line['quantity'],'unit_price'=>$line['unit_price'],'total_price'=>$line['total'],'notes'=>$line['notes']]);
                foreach($line['options'] as $option) $item->options()->create(['option_name'=>$option->name,'extra_price'=>$option->extra_price]);
            }
            DB::table('order_status_logs')->insert(['order_id'=>$o->id,'status'=>$o->status,'changed_by'=>$request->user()->id,'created_at'=>now()]);
            if($quote['promo']) PromoUsage::create(['promo_id'=>$quote['promo']->id,'user_id'=>$request->user()->id,'order_id'=>$o->id,'discount_amount'=>$quote['promo_discount'],'used_at'=>now()]);
            if(in_array($method,['cod','cash'],true)) Payment::create(['order_id'=>$o->id,'method'=>$method,'amount'=>$o->total,'status'=>'pending']);
            return $o;
        });
        Notifier::order($order,$order->status);
        $checkout=$method==='gcash'?app(PaymentGateway::class)->createCheckout($order):null;
        return $this->ok(['order'=>$this->clientOrder($order->fresh()->load(['items.options','review','payments'])),'checkout'=>$checkout], 'Nagawa ang order',201);
    }

    public function index(Request $request)
    {
        $orders=Order::with(['items.options','review','payments'])->where('user_id',$request->user()->id)->latest('id')->limit(100)->get();
        return $this->ok($orders->map(fn($o)=>$this->clientOrder($o))->values());
    }

    public function show(Request $request,int $id)
    {
        $o=Order::with(['items.options','review','payments'])->where('user_id',$request->user()->id)->findOrFail($id);
        return $this->ok($this->clientOrder($o));
    }

    public function pay(Request $request,int $id)
    {
        $o=Order::where('user_id',$request->user()->id)->findOrFail($id);
        if($o->payment_method!=='gcash') return $this->fail('COD ang bayad sa order na ito.');
        $checkout=app(PaymentGateway::class)->createCheckout($o);
        return $this->ok(array_merge($this->clientOrder($o->fresh()->load(['items.options','review','payments'])), ['checkout_url'=>$checkout['url']]));
    }

    public function verify(Request $request,int $id)
    {
        $o=Order::where('user_id',$request->user()->id)->findOrFail($id);
        app(PaymentGateway::class)->sync($o);
        return $this->ok($this->clientOrder($o->fresh()->load(['items.options','review','payments'])));
    }

    public function cancel(Request $request,int $id)
    {
        $o=Order::where('user_id',$request->user()->id)->findOrFail($id);
        if(!$o->canCancel()) return $this->fail('Hindi na maaaring kanselahin ang order.',409);
        $o->update(['status'=>'cancelled','reject_reason'=>'Kinansela ng customer']);
        $this->log($o,$request->user()->id);Notifier::order($o,'cancelled');
        return $this->ok($this->clientOrder($o->fresh()->load(['items.options','review','payments'])));
    }

    public function tracking(Request $request,int $id)
    {
        $o=Order::with(['delivery.rider.riderProfile','items.options'])->where('user_id',$request->user()->id)->findOrFail($id);
        $delivery=$o->delivery;$rider=null;
        if($delivery && $delivery->rider){$profile=$delivery->rider->riderProfile;$rider=['name'=>$delivery->rider->name,'phone'=>$delivery->rider->phone,'status'=>$delivery->status,'lat'=>$profile?->current_lat,'lng'=>$profile?->current_lng,'eta_min'=>null];}
        $timeline=DB::table('order_status_logs')->where('order_id',$o->id)->orderBy('id')->get()->map(fn($l)=>['status'=>$l->status,'at'=>$l->created_at])->values();
        return $this->ok(['order'=>$this->clientOrder($o),'rider'=>$rider,
            'destination'=>['lat'=>$o->delivery_lat,'lng'=>$o->delivery_lng],
            'shop'=>['lat'=>(float)Settings::get('shop_lat',0),'lng'=>(float)Settings::get('shop_lng',0)],'timeline'=>$timeline]);
    }

    public function review(Request $request,int $id)
    {
        $data=$request->validate(['rating'=>'required|integer|between:1,5','comment'=>'nullable|string|max:1000','product_id'=>'nullable|exists:products,id']);
        $o=Order::where('user_id',$request->user()->id)->whereIn('status',['delivered','completed'])->findOrFail($id);
        if($o->review()->exists()) return $this->fail('Naipadala na ang review para sa order na ito.',409);
        return $this->ok($o->review()->create($data+['user_id'=>$request->user()->id]),'Salamat sa review',201);
    }

    private function validateCheckout(Request $r,bool $forOrder):array
    {
        return $r->validate(['items'=>'required|array|min:1|max:50','items.*.product_id'=>'required|integer|exists:products,id',
            'items.*.quantity'=>'required|integer|min:1|max:50','items.*.option_ids'=>'nullable|array','items.*.option_ids.*'=>'integer',
            'items.*.notes'=>'nullable|string|max:255','order_type'=>($forOrder?'required':'nullable').'|in:delivery,pickup',
            'payment_method'=>'nullable|in:cod,cash,gcash','promo_code'=>'nullable|string|max:30',
            'delivery_address'=>'required_if:order_type,delivery|nullable|string|max:1000',
            'delivery_lat'=>'nullable|numeric|between:-90,90','delivery_lng'=>'nullable|numeric|between:-180,180','notes'=>'nullable|string|max:1000']);
    }

    private function quote(User $user,array $data):array
    {
        $lines=[];$subtotal=0.0;
        foreach($data['items'] as $input){
            $product=Product::with(['optionGroups.options'=>fn($q)=>$q->where('is_available',true)])
                ->where('is_available',true)->findOrFail($input['product_id']);
            $ids=array_values(array_unique(array_map('intval',$input['option_ids']??[])));
            $options=$product->optionGroups->flatMap->options->whereIn('id',$ids)->values();
            if($options->count()!==count($ids)) throw ValidationException::withMessages(['items'=>'May napiling option na hindi available para sa product.']);
            foreach($product->optionGroups as $group){
                $count=$options->where('group_id',$group->id)->count();
                if($group->is_required && $count===0) throw ValidationException::withMessages(['items'=>"Pumili ng option para sa {$product->name} ({$group->name})."]);
                if(!$group->is_multiple && $count>1 || $group->is_multiple && $count>(int)$group->max_select) throw ValidationException::withMessages(['items'=>"Sobra ang napiling option para sa {$group->name}."]);
            }
            $unit=(float)$product->base_price+(float)$options->sum('extra_price');$qty=(int)$input['quantity'];$total=round($unit*$qty,2);$subtotal+=$total;
            $lines[]=['product'=>$product,'options'=>$options,'quantity'=>$qty,'unit_price'=>$unit,'total'=>$total,'notes'=>$input['notes']??null];
        }
        $subtotal=round($subtotal,2);$isFirst=!Order::where('user_id',$user->id)->exists();$firstDiscount=0.0;
        if($isFirst && (bool)((int)Settings::get('first_order_enabled',1))){$firstDiscount=min($subtotal*(float)Settings::get('first_order_percent',15)/100,(float)Settings::get('first_order_max',100));}
        $promo=null;$promoDiscount=0.0;
        if(!empty($data['promo_code'])){
            $promo=PromoCode::live()->where('show_in_app',true)->whereRaw('upper(code)=?', [strtoupper($data['promo_code'])])->first();
            if(!$promo) throw ValidationException::withMessages(['promo_code'=>'Hindi valid o expired ang promo code.']);
            if($subtotal<(float)$promo->min_order) throw ValidationException::withMessages(['promo_code'=>'Hindi pa abot sa minimum order ang subtotal.']);
            if($promo->usage_limit && $promo->usages()->count()>=(int)$promo->usage_limit) throw ValidationException::withMessages(['promo_code'=>'Naabot na ang limit ng promo na ito.']);
            if($promo->usages()->where('user_id',$user->id)->count()>=(int)$promo->per_user_limit) throw ValidationException::withMessages(['promo_code'=>'Nagamit mo na ang limit ng promo na ito.']);
            if($promo->type==='percent') $promoDiscount=$subtotal*(float)$promo->value/100;
            elseif($promo->type==='fixed') $promoDiscount=(float)$promo->value;
            else { $eligible=collect($lines)->first(fn($line)=>$line['product']->id===$promo->product_id);if($eligible){$sets=intdiv($eligible['quantity'],(int)$promo->buy_qty+(int)$promo->free_qty);$promoDiscount=$sets*(int)$promo->free_qty*(float)$eligible['product']->base_price;} }
            if($promo->max_discount) $promoDiscount=min($promoDiscount,(float)$promo->max_discount);
            $promoDiscount=min($promoDiscount,$subtotal);
        }
        $discount=(bool)((int)Settings::get('allow_promo_stacking',0))?min($subtotal,$firstDiscount+$promoDiscount):max($firstDiscount,$promoDiscount);
        $label=$promoDiscount>$firstDiscount?($promo?->code):($firstDiscount>0?'First Order':null);
        $distance=0.0;$fee=0.0;
        if(($data['order_type']??'delivery')==='delivery'){
            if(isset($data['delivery_lat'],$data['delivery_lng']) && Settings::get('shop_lat')!==null && Settings::get('shop_lng')!==null){
                $distance=Geo::km((float)Settings::get('shop_lat'),(float)Settings::get('shop_lng'),(float)$data['delivery_lat'],(float)$data['delivery_lng']);
                if($distance>(float)Settings::get('delivery_radius_km',8)) throw ValidationException::withMessages(['delivery_lat'=>'Lampas sa delivery radius ang lokasyon.']);
            }
            $fee=(float)Settings::get('delivery_base_fee',30)+$distance*(float)Settings::get('delivery_fee_per_km',10);
        }
        return ['lines'=>$lines,'subtotal'=>$subtotal,'discount'=>round($discount,2),'discount_label'=>$label,'promo'=>$promo,
            'promo_discount'=>round($promoDiscount,2),'is_first_order'=>$isFirst,'distance_km'=>$distance,'delivery_fee'=>round($fee,2),
            'total'=>round(max(0,$subtotal-$discount)+$fee,2)];
    }

    private function clientOrder(Order $o):array
    {
        $o->loadMissing(['items.options','review','payments']);
        $payment=$o->payments->sortByDesc('id')->first();
        return ['id'=>$o->id,'order_number'=>$o->order_number,'order_type'=>$o->order_type,'status'=>$o->status,
            'subtotal'=>(float)$o->subtotal,'discount'=>(float)$o->discount,'delivery_fee'=>(float)$o->delivery_fee,'total'=>(float)$o->total,
            'payment_method'=>$o->payment_method,'payment_status'=>$o->payment_status,'delivery_address'=>$o->delivery_address,
            'delivery_lat'=>$o->delivery_lat,'delivery_lng'=>$o->delivery_lng,'notes'=>$o->notes,'reject_reason'=>$o->reject_reason,
            'created_at'=>$o->created_at?->toIso8601String(),'checkout_url'=>$payment?->checkout_url,
            'payment_expires_at'=>$o->created_at?->copy()->addMinutes((int)config('paymongo.timeout_minutes',30))->toIso8601String(),
            'can_pay'=>$o->payment_method==='gcash' && $o->payment_status==='unpaid' && $o->status==='awaiting_payment',
            'can_cancel'=>$o->canCancel(),'has_review'=>$o->review!==null,
            'items'=>$o->items->map(fn($i)=>['qty'=>$i->quantity,'name'=>$i->product_name,'line_total'=>(float)$i->total_price,'options'=>$i->options->pluck('option_name')->values()])->values()];
    }

    private function log(Order $o,?int $uid):void { DB::table('order_status_logs')->insert(['order_id'=>$o->id,'status'=>$o->status,'changed_by'=>$uid,'created_at'=>now()]); }
}




