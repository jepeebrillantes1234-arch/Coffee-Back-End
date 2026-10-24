<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $guarded = [];
    protected $casts = ['points_balance' => 'integer', 'activated_at' => 'datetime', 'expires_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function payments() { return $this->hasMany(MembershipPayment::class); }
    public function transactions() { return $this->hasMany(LoyaltyTransaction::class); }
    public function redemptions() { return $this->hasMany(RewardRedemption::class); }
    public function isActive(): bool { return $this->status === 'active' && (!$this->expires_at || $this->expires_at->isFuture()); }
}