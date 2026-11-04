<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MembershipCardDeliveryLocation extends Model {
    public $timestamps=false; protected $guarded=[];
    protected $casts=['latitude'=>'float','longitude'=>'float','accuracy'=>'float','recorded_at'=>'datetime'];
    public function delivery(){return $this->belongsTo(MembershipCardDelivery::class,'membership_card_delivery_id');}
    public function rider(){return $this->belongsTo(User::class,'rider_id');}
}
