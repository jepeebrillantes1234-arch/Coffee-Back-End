<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attachments' => 'array',
        'requested_amount_cents' => 'integer',
        'approved_amount_cents' => 'integer',
        'refunded_amount_cents' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function attempts() { return $this->hasMany(RefundAttempt::class)->orderBy('id'); }
}
