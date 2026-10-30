<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Payment;
use App\Support\Notifier;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderController extends Controller
{
    use Responds;

    public function profile(Request $r)
    {
        $user = $r->user()->load('riderProfile');
        return $this->ok([
            'user' => $user,
            'rider_profile' => $user->riderProfile,
        ]);
    }

    public function updateProfile(Request $r)
    {
        $data = $r->validate([
            'vehicle_type' => 'nullable|string|max:50',
            'plate_number' => 'nullable|string|max:20',
            'license_no' => 'nullable|string|max:50',
        ]);
        $profile = $r->user()->riderProfile;
        if (!$profile) return $this->fail('Rider profile is not configured for this account.', 403);
        $profile->update($data);
        return $this->ok($profile->fresh(), 'Vehicle information updated.');
    }
    public function index(Request $r)
    {
        $jobs=Delivery::with(['order.user:id,name,phone','order.items.options'])->where('rider_id',$r->user()->id)->whereIn('status',['assigned','accepted','picked_up','on_the_way'])->latest('id')->get();
        return $this->ok(['items'=>$jobs->map(fn($d)=>$this->resource($d))->values(),'is_available'=>(bool)$r->user()->riderProfile?->is_available]);
    }
    public function show(Request $r,int $id)
    {
        $d=Delivery::with(['order.user:id,name,phone','order.items.options'])->where('rider_id',$r->user()->id)->findOrFail($id);
        return $this->ok($this->resource($d));
    }
    public function accept(Request $r,int $id) { $d=$this->move($r,$id,'assigned','accepted','accepted_at');return $this->ok($this->resource($d)); }
    public function decline(Request $r,int $id)
    {
        $d=Delivery::with('order')->where('rider_id',$r->user()->id)->where('status','assigned')->whereHas('order',fn($q)=>$q->whereNotIn('status',['delivered','completed','cancelled','rejected']))->findOrFail($id);
        DB::transaction(function()use($d,$r){$d->update(['status'=>'declined']);$d->order->update(['status'=>'ready']);$this->log($d->order,$r->user()->id,'ready');});
        return $this->ok($d->fresh());
    }
    public function pickup(Request $r,int $id)
    {
        $d=$this->move($r,$id,'accepted','picked_up','picked_up_at');
        $d->order->update(['status'=>'picked_up']);$this->log($d->order,$r->user()->id,'picked_up');
        return $this->ok($this->resource($d->load('order.user','order.items.options')));
    }
    public function start(Request $r,int $id)
    {
        $d=$this->move($r,$id,'picked_up','on_the_way');
        $d->order->update(['status'=>'on_the_way']);$this->log($d->order,$r->user()->id,'on_the_way');
        return $this->ok($this->resource($d->load('order.user','order.items.options')));
    }
    public function delivered(Request $r,int $id)
    {
        $data=$r->validate(['proof_photo'=>'required|image|max:8192','cash_collected'=>'nullable|numeric|min:0']);
        $d=Delivery::with(['order.user','order.items.options'])->where('rider_id',$r->user()->id)->where('status','on_the_way')->whereHas('order',fn($q)=>$q->whereNotIn('status',['delivered','completed','cancelled','rejected']))->findOrFail($id);$o=$d->order;
        if($o->payment_method==='cod' && (float)($data['cash_collected']??0)<(float)$o->total) return $this->fail('Ilagay ang cash na natanggap; dapat katumbas o higit sa total.',422);
        DB::transaction(function()use($r,$data,$d,$o){
            $d->update(['status'=>'delivered','delivered_at'=>now(),'proof_photo'=>$r->file('proof_photo')->store('delivery-proofs','public'),'cash_collected_amount'=>$o->payment_method==='cod'?$o->total:null,'cash_collected_at'=>$o->payment_method==='cod'?now():null]);
            $o->update(['status'=>'delivered','payment_status'=>$o->payment_method==='cod'?'paid':$o->payment_status]);
            if($o->payment_method==='cod') Payment::updateOrCreate(['order_id'=>$o->id],['method'=>'cod','amount'=>$o->total,'status'=>'paid','paid_at'=>now()]);
            $this->log($o,$r->user()->id,'delivered');
        });
        Notifier::order($o->fresh(),'delivered');return $this->ok($this->resource($d->fresh()->load('order.user','order.items.options')),'Naitala ang delivery at proof.');
    }
    public function history(Request $r)
    {
        $base=Delivery::with(['order.user:id,name,phone','order.items.options'])->where('rider_id',$r->user()->id);
        $all=(clone $base)->where('status','delivered')->get();
        $jobs=(clone $base)->whereIn('status',['delivered','declined'])->latest('id')->limit(100)->get();
        $cash=Delivery::where('rider_id',$r->user()->id)->whereNotNull('cash_collected_at')->whereNull('cash_remitted_at')->sum('cash_collected_amount');
        return $this->ok(['items'=>$jobs->map(fn($d)=>$this->resource($d))->values(),'summary'=>[
            'today'=>$all->filter(fn($d)=>$d->delivered_at?->isToday())->count(),
            'total_deliveries'=>$all->count(),'total_km'=>round((float)$all->sum('distance_km'),2),'cash_on_hand'=>(float)$cash,
        ]]);
    }
    public function availability(Request $r)
    {
        $data=$r->validate(['is_available'=>'required|boolean']);
        $profile=$r->user()->riderProfile;
        if(!$profile) return $this->fail('Walang rider profile ang account na ito.',403);
        if(!$profile->is_approved) return $this->fail('Hindi pa aprubado ang rider account.',403);
        $profile->update($data);return $this->ok(['is_available'=>(bool)$profile->fresh()->is_available]);
    }
    public function location(Request $r)
    {
        $data=$r->validate(['delivery_id'=>'required|integer','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','speed'=>'nullable|numeric|min:0']);
        $d=Delivery::where('id',$data['delivery_id'])->where('rider_id',$r->user()->id)->whereIn('status',['accepted','picked_up','on_the_way'])->firstOrFail();
        DB::table('rider_locations')->insert(['delivery_id'=>$d->id,'rider_id'=>$r->user()->id,'latitude'=>$data['latitude'],'longitude'=>$data['longitude'],'speed'=>$data['speed']??null,'recorded_at'=>now()]);
        $r->user()->riderProfile()->update(['current_lat'=>$data['latitude'],'current_lng'=>$data['longitude'],'last_location_at'=>now()]);try{$d->loadMissing('order');\App\Events\LiveUpdate::dispatch('rider',(int)$r->user()->id,$d->order?->user_id ? (int)$d->order->user_id : null);}catch(\Throwable $exception){report($exception);}return $this->ok(null,'Naitala ang lokasyon.');
    }
    private function move(Request $r,int $id,string $from,string $to,?string $timestamp=null):Delivery
    {
        $d=Delivery::with('order')->where('rider_id',$r->user()->id)->where('status',$from)->whereHas('order',fn($q)=>$q->whereNotIn('status',['delivered','completed','cancelled','rejected']))->findOrFail($id);$fields=['status'=>$to];if($timestamp)$fields[$timestamp]=now();$d->update($fields);
        if($to==='accepted'){$d->order->update(['status'=>'rider_assigned']);$this->log($d->order,$r->user()->id,'rider_assigned');}
        return $d->fresh();
    }
    private function resource(Delivery $d):array
    {
        // A delivery can outlive its order (for example, an order was soft-deleted).
        // Keep rider history readable instead of throwing a 500 on a missing relation.
        $o=$d->order;
        $user=$o?->user;
        return ['id'=>$d->id,'status'=>$d->status,'distance_km'=>$d->distance_km,'proof_photo_url'=>$d->proof_photo_url,
            'customer'=>['name'=>$user?->name,'phone'=>$user?->phone],
            'shop'=>['lat'=>(float)Settings::get('shop_lat',0),'lng'=>(float)Settings::get('shop_lng',0)],
            'order'=>$o ? ['id'=>$o->id,'order_number'=>$o->order_number,'total'=>(float)$o->total,'payment_method'=>$o->payment_method,'payment_status'=>$o->payment_status,'address'=>$o->delivery_address,'lat'=>$o->delivery_lat,'lng'=>$o->delivery_lng,'notes'=>$o->notes,
                'items'=>$o->items->map(fn($i)=>['qty'=>$i->quantity,'name'=>$i->product_name,'options'=>$i->options->pluck('option_name')->values()])->values()] : null,
        ];
    }
    private function log($o,int $uid,string $status):void { DB::table('order_status_logs')->insert(['order_id'=>$o->id,'status'=>$status,'changed_by'=>$uid,'created_at'=>now()]); }
}
