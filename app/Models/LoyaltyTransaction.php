<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyTransaction extends Model
{
    protected $guarded = [];
    protected $casts = ['points' => 'integer'];
    public function membership() { return $this->belongsTo(Membership::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function redemption() { return $this->belongsTo(RewardRedemption::class); }
}