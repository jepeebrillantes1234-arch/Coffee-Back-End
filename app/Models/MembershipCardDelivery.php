<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MembershipCardDelivery extends Model {
    protected $guarded=[];
    protected $casts=['destination_latitude'=>'float','destination_longitude'=>'float','assigned_at'=>'datetime','preparing_at'=>'datetime','ready_at'=>'datetime','out_for_delivery_at'=>'datetime','delivered_at'=>'datetime'];
    public function membership(){return $this->belongsTo(Membership::class);}
    public function customer(){return $this->belongsTo(User::class,'user_id');}
    public function rider(){return $this->belongsTo(User::class,'rider_id');}
    public function locations(){return $this->hasMany(MembershipCardDeliveryLocation::class);}
}
