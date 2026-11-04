<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\MembershipCardDelivery;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class MembershipCardDeliveryController extends Controller {
    public function index(Request $r){$status=$r->query('status');$deliveries=MembershipCardDelivery::with(['customer:id,name,phone','rider:id,name','membership.user','membership.payments'])->when(in_array($status,['pending','preparing_card','ready_for_delivery','out_for_delivery','delivered'],true),fn($q)=>$q->where('status',$status))->latest('id')->paginate(25)->withQueryString();$active=Settings::get('membership_card_design_active','0')==='1';$front=Settings::get('membership_card_front_design')?:Settings::get('membership_card_design');$back=Settings::get('membership_card_back_design');$frontCardUrl=$active&&filled($front)?Storage::disk('public')->url($front):asset('images/membership-cards/card.png');$backCardUrl=$active&&filled($back)?Storage::disk('public')->url($back):asset('images/membership-cards/card1.png');return view('admin.membership-card-deliveries.index',compact('deliveries','status','frontCardUrl','backCardUrl'));}
}
