<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardRedemption extends Model
{
    protected $guarded = [];
    protected $casts = ['points_spent' => 'integer', 'claimed_at' => 'datetime', 'expires_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function membership() { return $this->belongsTo(Membership::class); }
    public function reward() { return $this->belongsTo(Reward::class); }
    public function claimer() { return $this->belongsTo(User::class, 'claimed_by'); }
}