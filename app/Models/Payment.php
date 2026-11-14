<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];
    public function order() { return $this->belongsTo(Order::class); }
    public function refundRequests() { return $this->hasMany(RefundRequest::class); }

    protected $casts = ['paid_at' => 'datetime', 'qr_expires_at' => 'datetime'];
}
