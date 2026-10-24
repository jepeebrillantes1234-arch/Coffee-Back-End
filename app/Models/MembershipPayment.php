<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPayment extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    public function membership() { return $this->belongsTo(Membership::class); }
}