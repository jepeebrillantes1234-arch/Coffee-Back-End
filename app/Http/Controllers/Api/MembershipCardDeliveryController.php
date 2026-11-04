<?php
namespace App\Http\Controllers\Api;
use App\Events\LiveUpdate;
use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Membership;
use App\Models\MembershipCardDelivery;
use App\Models\MembershipCardDeliveryLocation;
use App\Services\MembershipCardDeliveryRouter;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class MembershipCardDeliveryController extends Controller {
    use Responds;
    public function customerIndex(Request $r){$items=MembershipCardDelivery::where('user_id',$r->user()->id)->latest('id')->limit(20)->get()->map(fn($d)=>$this->resource($d,false))->values();return $this->ok(['items'=>$items]);}
    public function requestDelivery(Request $r){
        $data=$r->validate(['address_id'=>'required|integer','delivery_notes'=>'nullable|string|max:1000']);
        $delivery=DB::transaction(function()use($r,$data){
            $m=Membership::where('user_id',$r->user()->id)->lockForUpdate()->first();
            if(!$m||!$m->isActive()||!$m->payments()->where('status','paid')->exists())return null;
            if(MembershipCardDelivery::where('membership_id',$m->id)->exists())return false;
            $a=Address::where('user_id',$r->user()->id)->findOrFail($data['address_id']);
            $address=trim((string)($a->formatted_address?:$a->full_address));if($address==='')abort(422,'Select a saved delivery address with a complete address.');
            $notes=trim((string)($data['delivery_notes']??''));if($notes==='')$notes=trim((string)($a->notes??''));
            return MembershipCardDelivery::create(['membership_id'=>$m->id,'user_id'=>$r->user()->id,'requested_address_id'=>$a->id,
                'customer_name_snapshot'=>$r->user()->name,'customer_phone_snapshot'=>$r->user()->phone,'address_snapshot'=>$address,
                'street_snapshot'=>$a->street,'barangay_snapshot'=>$a->barangay,'city_snapshot'=>$a->city,'province_snapshot'=>$a->province,
                'postal_code_snapshot'=>$a->postal_code,'destination_latitude'=>$a->latitude,'destination_longitude'=>$a->longitude,
                'delivery_notes_snapshot'=>$notes!==''?$notes:null,'status'=>'pending']);
        });
        if($delivery===null)return $this->fail('An active membership with a verified paid membership payment is required.',409);
        if($delivery===false)return $this->fail('A membership card delivery request already exists for this membership.',409);
        try { LiveUpdate::dispatch('membership_card_delivery', (int) $delivery->id); } catch (\Throwable) { /* polling covers temporary Reverb outages */ }
        return $this->ok($this->resource($delivery,false),'Membership card delivery requested.',201);
    }
    public function riderIndex(Request $r){$items=MembershipCardDelivery::where('rider_id',$r->user()->id)->latest('id')->limit(100)->get()->map(fn($d)=>$this->resource($d,true))->values();return $this->ok(['items'=>$items]);}
    public function riderShow(Request $r,int $id){$d=MembershipCardDelivery::where('rider_id',$r->user()->id)->findOrFail($id);return $this->ok($this->resource($d,true));}
    public function startDelivery(Request $r,int $id){$d=DB::transaction(function()use($r,$id){$d=MembershipCardDelivery::where('rider_id',$r->user()->id)->lockForUpdate()->findOrFail($id);if($d->status!=='ready_for_delivery')return null;$d->update(['status'=>'out_for_delivery','out_for_delivery_at'=>now()]);return $d->fresh();});if(!$d)return $this->fail('This card is not ready to go out for delivery.',409);Activity::log('start_delivery','membership_card_deliveries',"Membership card delivery #{$d->id}: ready_for_delivery -> out_for_delivery.",(int)$r->user()->id);return $this->ok($this->resource($d,true),'Delivery started.');}
    public function markDelivered(Request $r,int $id){$d=DB::transaction(function()use($r,$id){$d=MembershipCardDelivery::where('rider_id',$r->user()->id)->lockForUpdate()->findOrFail($id);if($d->status!=='out_for_delivery')return null;$d->update(['status'=>'delivered','delivered_at'=>now()]);return $d->fresh();});if(!$d)return $this->fail('Only an active card delivery can be marked delivered.',409);Activity::log('complete_delivery','membership_card_deliveries',"Membership card delivery #{$d->id}: out_for_delivery -> delivered.",(int)$r->user()->id);return $this->ok($this->resource($d,true),'Membership card delivery completed.');}
    public function location(Request $r){
        $data=$r->validate(['delivery_id'=>'required|integer','latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','accuracy'=>'nullable|numeric|min:0|max:10000']);
        $d=MembershipCardDelivery::whereKey($data['delivery_id'])->where('rider_id',$r->user()->id)->where('status','out_for_delivery')->firstOrFail();
        MembershipCardDeliveryLocation::create(['membership_card_delivery_id'=>$d->id,'rider_id'=>$r->user()->id,'latitude'=>$data['latitude'],'longitude'=>$data['longitude'],'accuracy'=>$data['accuracy']??null,'recorded_at'=>now()]);
        return $this->ok(null,'Membership card delivery location recorded.');
    }
    private function resource(MembershipCardDelivery $d,bool $withRoute):array{
        $latest=$d->status==='out_for_delivery'?MembershipCardDeliveryLocation::where('membership_card_delivery_id',$d->id)->where('rider_id',$d->rider_id)->latest('recorded_at')->latest('id')->first():null;
        if($latest&&$latest->recorded_at->lt(now()->subSeconds(30)))$latest=null;
        $rider=$latest?['lat'=>(float)$latest->latitude,'lng'=>(float)$latest->longitude,'updated_at'=>$latest->recorded_at->toIso8601String()]:null;
        $dest=['lat'=>$d->destination_latitude,'lng'=>$d->destination_longitude];
        $route=$withRoute&&$d->status==='out_for_delivery'?app(MembershipCardDeliveryRouter::class)->route($d->id,$rider?['lat'=>$rider['lat'],'lng'=>$rider['lng']]:null,$dest['lat']!==null&&$dest['lng']!==null?$dest:null):['status'=>'unavailable','error'=>null,'points'=>[]];
        return ['id'=>$d->id,'status'=>$d->status,'assigned_at'=>$d->assigned_at?->toIso8601String(),'preparing_at'=>$d->preparing_at?->toIso8601String(),'ready_at'=>$d->ready_at?->toIso8601String(),'out_for_delivery_at'=>$d->out_for_delivery_at?->toIso8601String(),'delivered_at'=>$d->delivered_at?->toIso8601String(),
            'customer'=>['name'=>$d->customer_name_snapshot,'phone'=>$d->customer_phone_snapshot],
            'address'=>['full_address'=>$d->address_snapshot,'street'=>$d->street_snapshot,'barangay'=>$d->barangay_snapshot,'city'=>$d->city_snapshot,'province'=>$d->province_snapshot,'postal_code'=>$d->postal_code_snapshot,'lat'=>$dest['lat'],'lng'=>$dest['lng'],'notes'=>$d->delivery_notes_snapshot],
            'rider'=>$d->rider?['name'=>$d->rider->name]:null,'rider_location'=>$rider,'route'=>$route];
    }
}
