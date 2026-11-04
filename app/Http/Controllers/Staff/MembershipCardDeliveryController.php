<?php
namespace App\Http\Controllers\Staff;
use App\Http\Controllers\Controller;
use App\Models\MembershipCardDelivery;
use App\Models\RiderProfile;
use App\Models\User;
use App\Support\Settings;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class MembershipCardDeliveryController extends Controller {
    public function index(Request $r){
        $status=$r->query('status');$deliveries=MembershipCardDelivery::with(['customer:id,name,phone','rider:id,name','membership.user','membership.payments'])->when(in_array($status,['pending','preparing_card','ready_for_delivery','out_for_delivery','delivered'],true),fn($q)=>$q->where('status',$status))->latest('id')->paginate(25)->withQueryString();
        $riders=User::with('riderProfile')->where('role','rider')->where('status','active')->whereHas('riderProfile',fn($q)=>$q->where('is_approved',true)->where('is_available',true))->orderBy('name')->get(['id','name','phone']);
        [$frontCardUrl,$backCardUrl]=$this->cardArtworkUrls();
        return view('staff.membership-card-deliveries.index',compact('deliveries','riders','status','frontCardUrl','backCardUrl'));
    }
    public function notifications(Request $r){
        $data=$r->validate(['after_id'=>'sometimes|integer|min:0']);$afterId=(int)($data['after_id']??0);
        $map=fn($d)=>['id'=>(int)$d->id,'customer'=>$d->customer_name_snapshot,'member_id'=>$d->membership?->member_id,'requested_at'=>$d->created_at?->toIso8601String(),'url'=>$r->user()->role==='admin'?route('admin.membership-card-deliveries.index'):route('staff.membership-card-deliveries.index')];
        $new=MembershipCardDelivery::with('membership:id,member_id')->where('id','>',$afterId)->orderBy('id')->get(['id','membership_id','customer_name_snapshot','created_at'])->map($map)->values();
        $pending=MembershipCardDelivery::with('membership:id,member_id')->where('status','pending')->latest('id')->get(['id','membership_id','customer_name_snapshot','created_at'])->map($map)->values();
        return response()->json(['pending_count'=>$pending->count(),'latest_id'=>(int)(MembershipCardDelivery::max('id')??0),'new_requests'=>$new,'pending_requests'=>$pending])->header('Cache-Control','no-store, no-cache, must-revalidate');
    }
    public function preparing(Request $r,int $id){return $this->move($r,$id,'pending','preparing_card','prepared_by','preparing_at','Card preparation started.');}
    public function ready(Request $r,int $id){return $this->move($r,$id,'preparing_card','ready_for_delivery','ready_by','ready_at','Membership card is ready for delivery.');}
    public function assign(Request $r,int $id){
        $data=$r->validate(['rider_id'=>'required|integer|exists:users,id']);
        $result=DB::transaction(function()use($r,$id,$data){
            $d=MembershipCardDelivery::whereKey($id)->lockForUpdate()->firstOrFail();if($d->status!=='ready_for_delivery'||$d->rider_id)return false;
            $rider=User::whereKey($data['rider_id'])->where('role','rider')->where('status','active')->lockForUpdate()->first();
            $profile=$rider?RiderProfile::where('user_id',$rider->id)->lockForUpdate()->first():null;
            if(!$rider||!$profile||!$profile->is_approved||!$profile->is_available)return null;
            $d->update(['rider_id'=>$rider->id,'assigned_by'=>$r->user()->id,'assigned_at'=>now()]);return true;
        });
        if($result===false)return back()->with('error','This card delivery is no longer available for assignment.');
        if($result===null)return back()->with('error','The selected rider must be active, approved, and ONLINE.');
        Activity::log('assign_rider', 'membership_card_deliveries', "Membership card delivery #{$id} assigned to rider #{$data['rider_id']}.");
        return back()->with('success','Rider assigned to membership card delivery.');
    }
    private function move(Request $r,int $id,string $from,string $to,string $actor,string $time,string $message){
        $ok=DB::transaction(function()use($r,$id,$from,$to,$actor,$time){$d=MembershipCardDelivery::whereKey($id)->lockForUpdate()->firstOrFail();if($d->status!==$from)return false;$d->update(['status'=>$to,$actor=>$r->user()->id,$time=>now()]);return true;});
        if ($ok) Activity::log($to, 'membership_card_deliveries', "Membership card delivery #{$id}: {$from} -> {$to}.");
        return $ok?back()->with('success',$message):back()->with('error','This membership card delivery has already changed.');
    }
    private function cardArtworkUrls():array{
        $active=Settings::get('membership_card_design_active','0')==='1';$front=Settings::get('membership_card_front_design')?:Settings::get('membership_card_design');$back=Settings::get('membership_card_back_design');
        return [$active&&filled($front)?Storage::disk('public')->url($front):asset('images/membership-cards/card.png'),$active&&filled($back)?Storage::disk('public')->url($back):asset('images/membership-cards/card1.png')];
    }
}
